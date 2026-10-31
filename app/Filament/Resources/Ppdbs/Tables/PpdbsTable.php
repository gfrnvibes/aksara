<?php

namespace App\Filament\Resources\Ppdbs\Tables;

use App\Enums\JenisKelamin;
use App\Enums\StatusPpdb;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\Kelas;
use App\Models\Ppdb;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Ppdb\PenerimaanSiswaBaru;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class PpdbsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nomor_urut', 'desc')
            ->columns([
                TextColumn::make('nomor')
                    ->label('No. Daftar')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->description(fn (Ppdb $record): string => "NISN {$record->nisn}")
                    ->searchable(['nama_lengkap', 'nisn', 'nik'])
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('L/P')
                    ->formatStateUsing(fn (JenisKelamin $state): string => $state->value),
                TextColumn::make('asal_sekolah')
                    ->label('Asal Sekolah')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('berkas')
                    ->label('Berkas')
                    ->state(fn (Ppdb $record): string => count(array_filter($record->berkas ?? [])).'/'.count(Ppdb::BERKAS))
                    ->color(fn (Ppdb $record): string => count(array_filter($record->berkas ?? [])) === count(Ppdb::BERKAS) ? 'success' : 'warning')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Ppdb $record): ?string => $record->siswa ? "NIS {$record->siswa->nis}" : null)
                    ->url(fn (Ppdb $record): ?string => $record->siswa_id ? SiswaResource::getUrl('edit', ['record' => $record->siswa_id]) : null),
                TextColumn::make('created_at')
                    ->label('Mendaftar')
                    ->date('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->relationship('tahunAjaran', 'nama', fn (Builder $query) => $query->orderByDesc('nama'))
                    ->default(fn (): ?int => TahunAjaran::untukPpdb()?->id),
                SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options(JenisKelamin::class),
            ])
            ->recordActions([
                static::aksiLihatBerkas(),
                ActionGroup::make([
                    static::aksiUbahStatus(),
                    EditAction::make()
                        ->visible(fn (Ppdb $record): bool => $record->status !== StatusPpdb::JadiSiswa),
                    DeleteAction::make()
                        ->modalDescription('Data pendaftaran dan berkasnya dihapus permanen. Siswa yang sudah dibuat dari pendaftaran ini tetap ada di Buku Induk.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::aksiStatusMassal('verifikasiMassal', StatusPpdb::Terverifikasi, 'Verifikasi', Heroicon::OutlinedCheckBadge),
                    static::aksiStatusMassal('terimaMassal', StatusPpdb::Diterima, 'Terima', Heroicon::OutlinedCheckCircle),
                    static::aksiStatusMassal('cadanganMassal', StatusPpdb::Cadangan, 'Jadikan Cadangan', Heroicon::OutlinedClock),
                    static::aksiStatusMassal('tolakMassal', StatusPpdb::Ditolak, 'Tolak', Heroicon::OutlinedXCircle),
                    static::aksiJadikanSiswa(),
                    DeleteBulkAction::make()
                        ->modalDescription('Data pendaftaran dan berkasnya dihapus permanen. Siswa yang sudah dibuat dari pendaftaran ini tetap ada di Buku Induk.'),
                ]),
            ]);
    }

    public static function aksiLihatBerkas(): Action
    {
        return Action::make('lihatBerkas')
            ->label('Berkas')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (Ppdb $record): bool => array_filter($record->berkas ?? []) !== [])
            ->authorize('view')
            ->modalHeading(fn (Ppdb $record): string => "Berkas {$record->nomor} - {$record->nama_lengkap}")
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (Ppdb $record) => view('filament.arsip.pratinjau-berkas', ['berkas' => $record->berkasUntukPratinjau()]));
    }

    public static function aksiUbahStatus(): Action
    {
        return Action::make('ubahStatus')
            ->label('Ubah Status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Ppdb $record): bool => $record->status->bisaMenjadi() !== [])
            ->authorize('update')
            ->fillForm(fn (Ppdb $record): array => ['catatan' => $record->catatan])
            ->schema([
                Select::make('status')
                    ->options(fn (Ppdb $record): array => collect($record->status->bisaMenjadi())->mapWithKeys(fn (StatusPpdb $status): array => [$status->value => $status->getLabel()])->all())
                    ->live()
                    ->required(),
                Textarea::make('catatan')
                    ->label('Catatan Panitia')
                    ->rows(2)
                    ->required(fn (Get $get): bool => $get('status') === StatusPpdb::Ditolak->value),
            ])
            ->action(function (Ppdb $record, array $data): void {
                $tujuan = StatusPpdb::from($data['status']);

                if (in_array($tujuan, $record->status->bisaMenjadi(), true)) {
                    $record->update(['status' => $tujuan, 'catatan' => $data['catatan']]);
                }
            })
            ->successNotificationTitle('Status diperbarui');
    }

    /**
     * Moves the selected registrations to one status; those whose status cannot move there are skipped and counted.
     */
    public static function aksiStatusMassal(string $nama, StatusPpdb $tujuan, string $label, Heroicon $ikon): BulkAction
    {
        return BulkAction::make($nama)
            ->label($label)
            ->icon($ikon)
            ->color($tujuan->getColor())
            ->authorizeIndividualRecords('update')
            ->requiresConfirmation()
            ->modalHeading("{$label} pendaftar terpilih")
            ->modalDescription("Hanya pendaftar yang statusnya bisa diubah menjadi {$tujuan->getLabel()} yang diproses; sisanya dilewati.")
            ->schema($tujuan === StatusPpdb::Ditolak ? [
                Textarea::make('catatan')
                    ->label('Alasan')
                    ->rows(2)
                    ->required(),
            ] : [])
            ->action(function (BulkAction $action, Collection $records, array $data) use ($tujuan): void {
                foreach (static::pendaftarTerpilih($records) as $ppdb) {
                    if (! in_array($tujuan, $ppdb->status->bisaMenjadi(), true)) {
                        $action->reportBulkProcessingFailure('status_tidak_sesuai', message: fn (int $failureCount): string => "{$failureCount} pendaftar dilewati karena statusnya tidak bisa diubah menjadi {$tujuan->getLabel()}.");

                        continue;
                    }

                    $ppdb->update(['status' => $tujuan, ...(isset($data['catatan']) ? ['catatan' => $data['catatan']] : [])]);
                }
            })
            ->successNotificationTitle("Status pendaftar diubah menjadi {$tujuan->getLabel()}")
            ->failureNotificationTitle(fn (int $successCount, int $totalCount): string => "{$successCount} dari {$totalCount} pendaftar diubah menjadi {$tujuan->getLabel()}")
            ->deselectRecordsAfterCompletion();
    }

    /**
     * The selected rows as registrations.
     *
     * @param  Collection<array-key, Model>  $records
     * @return Collection<int, Ppdb>
     */
    protected static function pendaftarTerpilih(Collection $records): Collection
    {
        return Ppdb::query()->whereKey($records->modelKeys())->get();
    }

    /**
     * Enters the selected accepted applicants into the Buku Induk, each with the NIS the operator types, in one
     * first-grade class.
     */
    public static function aksiJadikanSiswa(): BulkAction
    {
        return BulkAction::make('jadikanSiswa')
            ->label('Jadikan Siswa Kelas '.PenerimaanSiswaBaru::TINGKAT_AWAL)
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->authorize(fn (): bool => Gate::allows('create', Siswa::class))
            ->authorizeIndividualRecords('update')
            ->modalHeading('Jadikan siswa di Buku Induk')
            ->modalDescription('Hanya pendaftar berstatus Diterima yang ditampilkan. Isi NIS masing-masing; hapus baris yang belum ingin diproses.')
            ->modalWidth('3xl')
            ->fillForm(fn (Collection $records): array => [
                'tanggal_diterima' => today()->toDateString(),
                'calon' => static::pendaftarTerpilih($records)
                    ->where('status', StatusPpdb::Diterima)
                    ->sortBy('nama_lengkap')
                    ->map(fn (Ppdb $ppdb): array => ['ppdb_id' => $ppdb->id, 'nama' => "{$ppdb->nama_lengkap} ({$ppdb->nisn})", 'nis' => null])
                    ->values()
                    ->all(),
            ])
            ->schema([
                Select::make('kelas_id')
                    ->label('Masukkan ke Kelas')
                    ->options(fn (): array => Kelas::query()->where('tingkat', PenerimaanSiswaBaru::TINGKAT_AWAL)->orderBy('nama')->pluck('nama', 'id')->all())
                    ->required(),
                DatePicker::make('tanggal_diterima')
                    ->label('Tanggal Diterima')
                    ->required(),
                Repeater::make('calon')
                    ->label('Calon Siswa')
                    ->table([
                        Repeater\TableColumn::make('Nama (NISN)'),
                        Repeater\TableColumn::make('NIS')->markAsRequired(),
                    ])
                    ->schema([
                        Hidden::make('ppdb_id'),
                        TextInput::make('nama')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('nis')
                            ->required()
                            ->maxLength(255)
                            ->distinct()
                            ->unique(table: 'siswas', column: 'nis'),
                    ])
                    ->addable(false)
                    ->reorderable(false)
                    ->minItems(1)
                    ->validationMessages(['min' => 'Tidak ada pendaftar berstatus Diterima yang dipilih.']),
            ])
            ->action(
                function (Collection $records, array $data): void {
                    $terpilih = static::pendaftarTerpilih($records)->keyBy('id');
                    $kelas = Kelas::query()->findOrFail((int) $data['kelas_id']);
                    $tanggal = Carbon::parse($data['tanggal_diterima']);
                    $penerimaan = app(PenerimaanSiswaBaru::class);
                    $berhasil = 0;
                    $gagal = [];

                    foreach ($data['calon'] as $calon) {
                        $ppdb = $terpilih->get((int) $calon['ppdb_id']);

                        if (! $ppdb instanceof Ppdb) {
                            continue;
                        }

                        try {
                            $penerimaan->jadikanSiswa($ppdb, trim((string) $calon['nis']), $kelas, $tanggal);
                            $berhasil++;
                        } catch (DomainException $exception) {
                            $gagal[] = $exception->getMessage();
                        }
                    }

                    Notification::make()
                        ->title("{$berhasil} siswa ditambahkan ke kelas {$kelas->nama}")
                        ->body($gagal === [] ? null : 'Dilewati: '.implode(' ', array_unique($gagal)))
                        ->status($gagal === [] ? 'success' : ($berhasil === 0 ? 'danger' : 'warning'))
                        ->when($gagal !== [], fn (Notification $notifikasi) => $notifikasi->persistent())
                        ->send();
                })
            ->deselectRecordsAfterCompletion();
    }
}
