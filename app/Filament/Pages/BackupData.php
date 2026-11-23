<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RiwayatRestore;
use App\Filament\Widgets\StatusBackup;
use App\Jobs\BuatBackup;
use App\Jobs\PulihkanBackup;
use App\Models\RiwayatBackup;
use App\Models\User;
use App\Services\BackupSekolah;
use App\Services\CatatanRestore;
use App\Services\RestoreBackup;
use App\Support\PemantauanBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Spatie\Backup\Helpers\Format;
use Throwable;
use UnitEnum;

/**
 * Lists the backup archives on every configured disk, shows their health, and lets authorised users
 * create, download and delete backups.
 *
 * Backups hold every student's personal data, so access requires the explicit Shield permission.
 */
class BackupData extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * The Shield permission generated for this page.
     */
    public const string IZIN = 'View:BackupData';

    /**
     * Seconds the remote (Google Drive) listing is cached, so the page stays fast.
     */
    public const int CACHE_DETIK = 300;

    /**
     * Seconds a download link stays valid.
     */
    public const int MASA_BERLAKU_UNDUHAN = 300;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Backup Data';

    protected static ?string $title = 'Backup Data';

    protected static ?string $slug = 'backup';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(static::IZIN) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public static function getJenisBackup(): array
    {
        return [
            BackupType::ONLY_DATABASE->value => 'Database saja',
            BackupType::DATABASE_AND_FILES->value => 'Database + semua berkas',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.restore-progress-banner'),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array{id: string, status: string, aktif: bool, persen: int, label: string, arsip: string, error: string|null}|null
     */
    public function getStatusRestoreTerbaru(): ?array
    {
        if (! static::canRestore()) {
            return null;
        }

        $record = app(CatatanRestore::class)->semua()->first();
        if (! is_array($record)) {
            return null;
        }

        $status = (string) ($record['status'] ?? '');
        $aktif = in_array($status, ['menunggu', 'validasi', 'backup_pengaman', 'diproses'], true);

        if (! $aktif) {
            $finishedAt = $record['finished_at'] ?? null;
            if (! is_string($finishedAt) || Carbon::parse($finishedAt)->lt(now()->subMinutes(10))) {
                return null;
            }
        }

        return [
            'id' => (string) ($record['id'] ?? ''),
            'status' => $status,
            'aktif' => $aktif,
            'persen' => match ($status) {
                'menunggu' => 10,
                'validasi' => 30,
                'backup_pengaman' => 55,
                'diproses' => 80,
                'berhasil', 'gagal' => 100,
                default => 10,
            },
            'label' => match ($status) {
                'menunggu' => 'Menunggu proses restore dimulai...',
                'validasi' => 'Memvalidasi dan mengekstrak arsip backup...',
                'backup_pengaman' => 'Membuat backup pengaman sebelum menimpa data...',
                'diproses' => 'Memulihkan database dan berkas...',
                'berhasil' => 'Restore selesai dipulihkan.',
                'gagal' => 'Restore gagal.',
                default => ucfirst($status),
            },
            'arsip' => basename((string) ($record['path'] ?? '')),
            'error' => is_string($record['error'] ?? null) ? $record['error'] : null,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatusBackup::class,
        ];
    }

    public static function canRestore(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && static::canAccess() && $user->hasRole(config('filament-shield.super_admin.name'));
    }

    protected function getFooterWidgets(): array
    {
        return [RiwayatRestore::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatBackup')
                ->label('Buat Backup Sekarang')
                ->icon(Heroicon::Plus)
                ->modalHeading('Buat Backup Sekarang')
                ->modalDescription('Setiap tujuan penyimpanan dicatat di tabel, termasuk jika backup gagal. Proses dimulai setelah permintaan ini selesai.')
                ->modalSubmitActionLabel('Mulai Backup')
                ->schema([
                    Radio::make('jenis')
                        ->label('Isi backup')
                        ->options(static::getJenisBackup())
                        ->default(BackupType::DATABASE_AND_FILES->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $jenis = BackupType::from($data['jenis']);

                    $backup = app(BackupSekolah::class);
                    $user = Filament::auth()->user();
                    $batch = $backup->mulai($jenis, 'manual', $user instanceof User ? $user->id : null);

                    try {
                        BuatBackup::dispatch($batch)->onConnection(config('backup.queue_connection'));
                    } catch (Throwable $exception) {
                        $backup->gagal($batch, $exception->getMessage());
                        Notification::make()->title('Backup gagal dimulai')->body('Lihat kolom kesalahan pada tabel.')->danger()->send();

                        return;
                    }

                    activity()->event('backup_dibuat')->withProperties(['jenis' => $jenis->value, 'batch' => $batch])->log('Backup manual dimulai');

                    Notification::make()
                        ->title('Backup sedang diproses')
                        ->body('Status setiap tujuan akan diperbarui otomatis pada tabel.')
                        ->success()
                        ->send();
                }),
            Action::make('restoreUnggah')
                ->label('Restore dari File')
                ->icon(Heroicon::ArrowUpTray)
                ->color('warning')
                ->visible(fn (): bool => static::canRestore())
                ->modalHeading('Restore data dari file backup?')
                ->modalDescription('Unggah arsip .zip hasil backup Aksara. Data saat ini akan diganti setelah backup pengaman dibuat dan aplikasi masuk maintenance sementara. Akun serta password mengikuti backup; Anda mungkin perlu login ulang.')
                ->modalSubmitActionLabel('Restore sekarang')
                ->schema([
                    FileUpload::make('arsip')
                        ->label('File arsip backup (.zip)')
                        ->disk('local')
                        ->directory(RestoreBackup::DIREKTORI_UNGGAH)
                        ->visibility('private')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/x-zip'])
                        ->maxSize(2097152)
                        ->required(),
                    Toggle::make('files')
                        ->label('Pulihkan foto/file siswa juga')
                        ->helperText('Aktifkan hanya jika arsip ZIP merupakan backup lengkap dan Anda ingin mengganti foto/file siswa.')
                        ->default(false),
                    TextInput::make('password_arsip')
                        ->label('Password arsip (opsional)')
                        ->password()
                        ->helperText('Kosongkan jika arsip tidak berpassword atau memakai password backup server ini.')
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    TextInput::make('konfirmasi')
                        ->label('Ketik RESTORE untuk melanjutkan')
                        ->required()
                        ->rules(['in:RESTORE']),
                    TextInput::make('password')
                        ->label('Password akun Anda')
                        ->password()
                        ->required()
                        ->currentPassword(guard: Filament::getAuthGuard())
                        ->dehydrated(false),
                ])
                ->action(function (array $data): void {
                    static::jadwalkanRestore(
                        RestoreBackup::DISK_UNGGAH,
                        (string) $data['arsip'],
                        (bool) ($data['files'] ?? false),
                        $data['password_arsip'] ?? null,
                    );
                }),
            Action::make('bersihkan')
                ->label('Bersihkan Backup Lama')
                ->icon(Heroicon::Sparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Backup lama dihapus sesuai aturan masa simpan (semua backup 7 hari terakhir, lalu harian, mingguan, bulanan, dan tahunan). Backup terbaru tidak pernah dihapus.')
                ->action(function (): void {
                    Artisan::queue('backup:clean')->onConnection(config('backup.queue_connection'));

                    Notification::make()->title('Pembersihan backup lama sedang diproses')->success()->send();
                }),
            Action::make('muatUlang')
                ->label('Muat Ulang')
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->action(function (): void {
                    PemantauanBackup::clearBackupDestinationCaches();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultPaginationPageOption(25)
            ->records(fn (array $filters): Collection => static::daftarBackup()->filter(
                fn (array $record): bool => (blank($filters['disk']['value'] ?? null) || $record['disk'] === $filters['disk']['value'])
                    && (blank($filters['type']['value'] ?? null) || $record['type'] === $filters['type']['value'])
                    && (blank($filters['status']['value'] ?? null) || $record['status'] === $filters['status']['value']),
            ))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i'),
                TextColumn::make('disk')
                    ->label('Lokasi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::labelDisk($state)),
                TextColumn::make('type')
                    ->label('Isi')
                    ->formatStateUsing(fn (string $state): string => static::getJenisBackup()[$state] ?? 'File saja'),
                TextColumn::make('size')
                    ->label('Ukuran'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'berhasil' => 'success', 'gagal' => 'danger', 'diproses' => 'info', default => 'warning',
                    }),
                TextColumn::make('source')->label('Pemicu'),
                TextColumn::make('error')
                    ->label('Kesalahan')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(160)
                    ->tooltip(fn (array $record): ?string => $record['error'])
                    ->color('danger'),
                TextColumn::make('path')
                    ->label('File')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('disk')
                    ->label('Lokasi')
                    ->options(collect(FilamentSpatieLaravelBackup::getDisks())->mapWithKeys(fn (string $disk): array => [$disk => static::labelDisk($disk)])->all()),
                SelectFilter::make('type')
                    ->label('Isi')
                    ->options(static::getJenisBackup()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal']),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Restore')->icon(Heroicon::ArrowPath)->color('warning')
                    ->visible(fn (array $record): bool => static::canRestore() && $record['available'] && $record['status'] === 'berhasil')
                    ->modalHeading('Pulihkan data dari backup?')
                    ->modalDescription('Data saat ini akan diganti. Sistem membuat backup pengaman terlebih dahulu dan masuk maintenance sementara. Akun serta password mengikuti backup; Anda mungkin perlu login ulang.')
                    ->modalSubmitActionLabel('Restore sekarang')
                    ->schema([
                        Toggle::make('files')->label('Pulihkan semua berkas juga')
                            ->helperText('Mengganti seluruh berkas di storage (public & private) sesuai backup.')
                            ->visible(fn (array $record): bool => $record['type'] === BackupType::DATABASE_AND_FILES->value)
                            ->default(false),
                        TextInput::make('konfirmasi')->label('Ketik RESTORE untuk melanjutkan')->required()->rules(['in:RESTORE']),
                        TextInput::make('password')->label('Password akun Anda')->password()->required()
                            ->currentPassword(guard: Filament::getAuthGuard())->dehydrated(false),
                    ])
                    ->action(function (array $record, array $data): void {
                        static::jadwalkanRestore($record['disk'], $record['path'], (bool) ($data['files'] ?? false));
                    }),
                Action::make('unduh')
                    ->visible(fn (array $record): bool => $record['available'])
                    ->label('Unduh')
                    ->icon(Heroicon::ArrowDownTray)
                    ->url(fn (array $record): string => URL::temporarySignedRoute(
                        'backup.unduh',
                        now()->addSeconds(static::MASA_BERLAKU_UNDUHAN),
                        ['disk' => $record['disk'], 'path' => $record['path']],
                    )),
                Action::make('hapus')
                    ->visible(fn (array $record): bool => $record['available'])
                    ->label('Hapus')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus file backup?')
                    ->modalDescription(fn (array $record): string => "File {$record['path']} di ".static::labelDisk($record['disk']).' akan dihapus permanen.')
                    ->action(function (array $record): void {
                        abort_unless(in_array($record['disk'], FilamentSpatieLaravelBackup::getDisks(), true), 404);

                        Storage::disk($record['disk'])->delete($record['path']);
                        FilamentSpatieLaravelBackup::clearBackupDestinationCaches();

                        activity()->event('backup_dihapus')->withProperties(['disk' => $record['disk'], 'path' => $record['path']])->log('File backup dihapus');

                        Notification::make()->title('File backup dihapus')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada backup')
            ->emptyStateDescription('Klik "Buat Backup Sekarang" atau tunggu backup terjadwal malam ini.');
    }

    public static function labelDisk(string $disk): string
    {
        return match ($disk) {
            'local' => 'Server (lokal)',
            'google' => 'Google Drive',
            'r2' => 'Cloudflare R2',
            RestoreBackup::DISK_UNGGAH => 'Unggahan file',
            default => ucfirst($disk),
        };
    }

    protected static function jadwalkanRestore(string $disk, string $path, bool $files, ?string $archivePassword = null): void
    {
        abort_unless(static::canRestore(), 403);
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);
        $restore = app(RestoreBackup::class);
        $id = null;

        try {
            $id = filled($archivePassword)
                ? $restore->mulai($user, $disk, $path, $files, $archivePassword)
                : $restore->mulai($user, $disk, $path, $files);
            // The queue database itself will be restored: do not enqueue this job in it.
            PulihkanBackup::dispatch($id)->onConnection('deferred');
            Notification::make()
                ->title('Restore dijadwalkan')
                ->body('Aplikasi masuk maintenance saat proses berjalan. Setelah selesai, muat ulang dan login kembali.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            if ($id !== null) {
                $restore->gagal($id, $exception->getMessage());
            } elseif ($disk === RestoreBackup::DISK_UNGGAH) {
                Storage::disk('local')->delete($path);
            }
            Notification::make()
                ->title('Restore gagal dimulai')
                ->body(app(BackupSekolah::class)->pesanKesalahan($exception->getMessage()))
                ->danger()
                ->send();
        }
    }

    /**
     * Every backup archive on every configured disk, newest first, keyed by a stable record key.
     *
     * @return Collection<array-key, array{key: string, disk: string, path: string|null, type: string, date: string, size: string, status: string, source: string, error: string|null, available: bool}>
     */
    protected static function daftarBackup(): Collection
    {
        $files = collect(FilamentSpatieLaravelBackup::getDisks())
            ->flatMap(fn (string $disk): array => FilamentSpatieLaravelBackup::getBackupDestinationData($disk, static::CACHE_DETIK))
            ->map(fn (array $file): array => [...$file, 'status' => 'berhasil', 'source' => 'Arsip', 'error' => null, 'available' => true])
            ->keyBy('key');

        $history = RiwayatBackup::query()->latest('id')->get()->map(function (RiwayatBackup $backup) use ($files): array {
            $key = $backup->path ? sha1($backup->disk."\0".$backup->path) : 'riwayat:'.$backup->id;

            return [
                'key' => $key, 'disk' => $backup->disk, 'path' => $backup->path,
                'type' => $backup->jenis, 'date' => $backup->created_at->format('Y-m-d H:i:s'),
                'size' => $backup->ukuran === null ? '—' : Format::humanReadableSize($backup->ukuran),
                'status' => $backup->status, 'source' => ucfirst($backup->sumber),
                'error' => $backup->kesalahan, 'available' => $files->has($key),
            ];
        })->keyBy('key');

        return $files->merge($history)->sortByDesc('date');
    }
}
