<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatusBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
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
            BackupType::DATABASE_AND_FILES->value => 'Database + file (foto siswa)',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatusBackup::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatBackup')
                ->label('Buat Backup Sekarang')
                ->icon(Heroicon::Plus)
                ->modalHeading('Buat Backup Sekarang')
                ->modalDescription('Backup diproses di latar belakang dan akan muncul di daftar dalam beberapa menit.')
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

                    CreateBackupJob::dispatch($jenis, timeout: 1800);

                    activity()->event('backup_dibuat')->withProperties(['jenis' => $jenis->value])->log('Backup manual dimulai');

                    Notification::make()
                        ->title('Backup sedang diproses')
                        ->body('Muat ulang halaman ini dalam beberapa menit untuk melihat hasilnya.')
                        ->success()
                        ->send();
                }),
            Action::make('bersihkan')
                ->label('Bersihkan Backup Lama')
                ->icon(Heroicon::Sparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Backup lama dihapus sesuai aturan masa simpan (semua backup 7 hari terakhir, lalu harian, mingguan, bulanan, dan tahunan). Backup terbaru tidak pernah dihapus.')
                ->action(function (): void {
                    Artisan::queue('backup:clean');

                    Notification::make()->title('Pembersihan backup lama sedang diproses')->success()->send();
                }),
            Action::make('muatUlang')
                ->label('Muat Ulang')
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->action(function (): void {
                    FilamentSpatieLaravelBackup::clearBackupDestinationCaches();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters): Collection => static::daftarBackup()
                ->when(
                    filled($filters['disk']['value'] ?? null),
                    fn (Collection $backup): Collection => $backup->where('disk', $filters['disk']['value']),
                )
                ->when(
                    filled($filters['type']['value'] ?? null),
                    fn (Collection $backup): Collection => $backup->where('type', $filters['type']['value']),
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
            ])
            ->recordActions([
                Action::make('unduh')
                    ->label('Unduh')
                    ->icon(Heroicon::ArrowDownTray)
                    ->url(fn (array $record): string => URL::temporarySignedRoute(
                        'backup.unduh',
                        now()->addSeconds(static::MASA_BERLAKU_UNDUHAN),
                        ['disk' => $record['disk'], 'path' => $record['path']],
                    )),
                Action::make('hapus')
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
            default => ucfirst($disk),
        };
    }

    /**
     * Every backup archive on every configured disk, newest first, keyed by a stable record key.
     *
     * @return Collection<string, array{key: string, disk: string, path: string, type: string, date: string, size: string}>
     */
    protected static function daftarBackup(): Collection
    {
        return collect(FilamentSpatieLaravelBackup::getDisks())
            ->flatMap(fn (string $disk): array => FilamentSpatieLaravelBackup::getBackupDestinationData($disk, static::CACHE_DETIK))
            ->sortByDesc('date')
            ->keyBy('key');
    }
}
