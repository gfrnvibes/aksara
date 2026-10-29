<?php

namespace App\Filament\Resources\Rapors\Tables;

use App\Enums\Semester;
use App\Models\Rapor;
use App\Models\TahunAjaran;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaporsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::tambahKolomGrup($query
                ->with(['siswa', 'tahunAjaran', 'kelas'])
                ->scopes(['denganRataRataNilai'])))
            ->groups([
                Group::make('grup_kelas')
                    ->label('Tahun Ajaran, Semester & Kelas')
                    ->getTitleFromRecordUsing(fn (Rapor $record): string => "{$record->tahunAjaran->nama} · {$record->semester->getLabel()} · {$record->kelas->nama}")
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderByDesc('tahun_grup.nama')->orderBy('rapors.semester')->orderBy('kelas_grup.tingkat')->orderBy('kelas_grup.nama'))
                    ->scopeQueryByKeyUsing(function (Builder $query, mixed $key): Builder {
                        [$tahunAjaranId, $semester, $kelasId] = array_map(intval(...), array_pad(explode('-', (string) $key), 3, 0));

                        return $query->where('rapors.tahun_ajaran_id', $tahunAjaranId)->where('rapors.semester', $semester)->where('rapors.kelas_id', $kelasId);
                    })
                    ->collapsible(),
                Group::make('siswa_id')
                    ->label('Siswa')
                    ->getTitleFromRecordUsing(fn (Rapor $record): string => "{$record->siswa->nis} - {$record->siswa->nama_lengkap}")
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('siswa_grup.nama_lengkap')->orderBy('rapors.siswa_id'))
                    ->scopeQueryByKeyUsing(fn (Builder $query, mixed $key): Builder => $query->where('rapors.siswa_id', $key))
                    ->collapsible(),
            ])
            ->defaultGroup('grup_kelas')
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('siswa_grup.nama_lengkap')->orderByDesc('tahun_grup.nama')->orderBy('rapors.semester'))
            ->columns([
                TextColumn::make('siswa.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Nama Siswa')
                    ->searchable(),
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran'),
                TextColumn::make('semester')
                    ->sortable(),
                TextColumn::make('kelas.nama')
                    ->label('Kelas'),
                TextColumn::make('rata_rata_nilai')
                    ->label('Rata-rata')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('keputusan')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran')
                    ->label('Tahun Ajaran')
                    ->relationship('tahunAjaran', 'nama', fn (Builder $query): Builder => $query->orderByDesc('nama'))
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->preload(),
                SelectFilter::make('semester')
                    ->options(Semester::class),
                SelectFilter::make('kelas')
                    ->relationship('kelas', 'nama')
                    ->preload(),
                TrashedFilter::make()
                    ->label('Data terhapus'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('cetakRapor')
                        ->label('Cetak di browser')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Rapor $record): string => route('rapor.cetak', $record))
                        ->openUrlInNewTab(),
                    Action::make('unduhRapor')
                        ->label('Unduh PDF')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->url(fn (Rapor $record): string => route('rapor.cetak', ['rapor' => $record, 'format' => 'pdf'])),
                ])
                    ->label('Cetak Rapor')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->link()
                    ->visible(fn (Rapor $record): bool => ! $record->trashed()),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Rapor dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
                RestoreAction::make()
                    ->label('Pulihkan'),
                ForceDeleteAction::make()
                    ->label('Hapus permanen')
                    ->modalDescription('Rapor beserta seluruh nilai mata pelajaran dan ekstrakurikulernya akan dihapus permanen dan tidak dapat dipulihkan.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Rapor terpilih dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->modalDescription('Rapor terpilih beserta seluruh nilainya akan dihapus permanen dan tidak dapat dipulihkan.'),
                ]),
            ]);
    }

    /**
     * Join what the rows are grouped and sorted by — the academic year, the class and the student — and add
     * `grup_kelas`, one key per academic year, semester and class.
     *
     * @param  Builder<Rapor>  $query
     * @return Builder<Rapor>
     */
    public static function tambahKolomGrup(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('rapors.*');
        }

        return $query
            ->leftJoin('tahun_ajarans as tahun_grup', 'tahun_grup.id', '=', 'rapors.tahun_ajaran_id')
            ->leftJoin('kelas as kelas_grup', 'kelas_grup.id', '=', 'rapors.kelas_id')
            ->leftJoin('siswas as siswa_grup', 'siswa_grup.id', '=', 'rapors.siswa_id')
            ->selectRaw("concat(rapors.tahun_ajaran_id, '-', rapors.semester, '-', rapors.kelas_id) as grup_kelas");
    }
}
