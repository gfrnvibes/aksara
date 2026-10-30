<?php

namespace App\Filament\Resources\Siswas\Tables;

use App\Enums\JenisKelamin;
use App\Enums\StatusSiswa;
use App\Filament\Actions\CetakSuratActions;
use App\Filament\Actions\EksporBukuIndukActions;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SiswasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, HasTable $livewire): Builder => static::tambahKelasGrup(
                $query,
                filled($id = $livewire->getTableFilterState('tahun_ajaran')['value'] ?? null) ? TahunAjaran::query()->find((int) $id) : null,
            ))
            ->groups([
                Group::make('kelas_grup_id')
                    ->label('Kelas')
                    ->getTitleFromRecordUsing(fn (Siswa $record): string => collect([$record->getAttribute('tahun_grup'), $record->getAttribute('kelas_grup_nama') ?? 'Tanpa kelas'])->filter()->implode(' · '))
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderByRaw('kelas_grup.tingkat asc nulls last')->orderBy('kelas_grup.nama'))
                    ->scopeQueryByKeyUsing(fn (Builder $query, mixed $key): Builder => filled($key) ? $query->where('kelas_grup.id', $key) : $query->whereNull('kelas_grup.id'))
                    ->collapsible(),
                Group::make('status')
                    ->getTitleFromRecordUsing(fn (Siswa $record): string => $record->status->getLabel())
                    ->collapsible(),
            ])
            ->defaultGroup('kelas_grup_id')
            ->defaultSort('siswas.nama_lengkap')
            ->defaultPaginationPageOption(25)
            ->columns([
                ImageColumn::make('foto')
                    ->disk(fn (): string => Siswa::diskFoto())
                    ->visibility('private')
                    ->circular(),
                TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('L/P')
                    ->formatStateUsing(fn (JenisKelamin $state): string => $state->value),
                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('rapor_count')
                    ->label('Rapor')
                    ->counts('rapor')
                    ->suffix(' semester')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->query(function (Builder $query, array $data): Builder {
                        $tahunAjaran = filled($data['value'] ?? null) ? TahunAjaran::query()->find((int) $data['value']) : null;

                        return $tahunAjaran === null ? $query : $query->scopes(['anggotaTahunAjaran' => [$tahunAjaran]]);
                    }),
                SelectFilter::make('kelas')
                    ->label('Kelas saat ini')
                    ->relationship('kelas', 'nama')
                    ->preload(),
                SelectFilter::make('status')
                    ->options(StatusSiswa::class),
                SelectFilter::make('jenis_kelamin')
                    ->options(JenisKelamin::class),
                TrashedFilter::make()
                    ->label('Data terhapus'),
            ])
            ->recordActions([
                EditAction::make(),
                EksporBukuIndukActions::cetakSatuSiswa(),
                CetakSuratActions::group(),
                RestoreAction::make()
                    ->label('Pulihkan'),
                ForceDeleteAction::make()
                    ->label('Hapus permanen')
                    ->modalDescription('Biodata, data orang tua/wali, dan seluruh nilai rapor siswa ini akan dihapus permanen dan tidak dapat dipulihkan.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    EksporBukuIndukActions::exportSiswaTerpilih(),
                    EksporBukuIndukActions::cetakSiswaTerpilih(),
                    DeleteBulkAction::make()
                        ->modalDescription('Siswa terpilih dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->modalDescription('Biodata, data orang tua/wali, dan seluruh nilai rapor siswa terpilih akan dihapus permanen dan tidak dapat dipulihkan.'),
                ]),
            ]);
    }

    /**
     * Join the class the student sat in during the chosen academic year as `kelas_grup` — from the class history,
     * falling back to the current class in the active year — or the current class when no year is chosen.
     *
     * @param  Builder<Siswa>  $query
     * @return Builder<Siswa>
     */
    public static function tambahKelasGrup(Builder $query, ?TahunAjaran $tahunAjaran): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('siswas.*');
        }

        if ($tahunAjaran !== null) {
            $query->leftJoin('riwayat_kelas as riwayat_grup', fn ($join) => $join
                ->on('riwayat_grup.siswa_id', '=', 'siswas.id')
                ->where('riwayat_grup.tahun_ajaran_id', '=', $tahunAjaran->id));
        }

        $kelasId = match (true) {
            $tahunAjaran === null => 'siswas.kelas_id',
            $tahunAjaran->is_aktif => DB::raw('coalesce(riwayat_grup.kelas_id, siswas.kelas_id)'),
            default => 'riwayat_grup.kelas_id',
        };

        return $query
            ->leftJoin('kelas as kelas_grup', 'kelas_grup.id', '=', $kelasId)
            ->addSelect(['kelas_grup.id as kelas_grup_id', 'kelas_grup.nama as kelas_grup_nama'])
            ->selectRaw('? as tahun_grup', [$tahunAjaran?->nama]);
    }
}
