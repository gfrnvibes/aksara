<?php

namespace App\Services;

use App\Models\User;
use App\Support\PemantauanBackup;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RestoreBackup
{
    public function __construct(protected CatatanRestore $catatan, protected ArsipRestore $arsip, protected PemulihanDatabase $database) {}

    public function mulai(User $user, string $disk, string $path, bool $files): string
    {
        $this->otorisasi($user);
        if (config('app.maintenance.driver') !== 'file') {
            throw new RuntimeException('Gunakan APP_MAINTENANCE_DRIVER=file sebelum menjalankan restore agar maintenance tetap aktif ketika database diganti.');
        }
        $this->validasiLokasi($disk, $path);
        $this->database->validasi($this->connection());

        return $this->catatan->buat([
            'actor_id' => $user->id, 'actor' => $user->name, 'disk' => $disk, 'path' => $path, 'files' => $files,
        ]);
    }

    public function jalankan(string $id): void
    {
        $record = $this->catatan->ambil($id);
        if ($record['status'] !== 'menunggu') {
            return;
        }
        $lock = Cache::lock('aksara:backup', 3600);
        if (! $lock->get()) {
            $this->gagal($id, 'Backup atau restore lain masih berjalan.');

            return;
        }
        if ($this->catatan->ambil($id)['status'] !== 'menunggu') {
            $lock->release();

            return;
        }
        $work = $this->temporaryRoot().'/'.$id;
        $maintenance = false;
        $databaseChanged = false;
        $filesChanged = false;
        $safety = null;
        $keepRecovery = false;

        try {
            $this->otorisasi(User::query()->findOrFail((int) $record['actor_id']));
            $this->validasiLokasi((string) $record['disk'], (string) $record['path']);
            $connection = $this->connection();
            $this->database->validasi($connection);
            if (app()->isDownForMaintenance() || File::exists($this->marker())) {
                throw new RuntimeException('Aplikasi sedang maintenance atau restore lain belum selesai.');
            }
            File::ensureDirectoryExists($work);
            $this->catatan->ubah($id, ['status' => 'validasi']);
            $this->salinArsip((string) $record['disk'], (string) $record['path'], $work.'/archive.zip');
            $source = $this->arsip->ekstrak($work.'/archive.zip', $work.'/source', $connection->getDriverName());
            if ($record['files'] && ! $source['has_files']) {
                throw new RuntimeException('Arsip tidak memuat foto/file. Pilih restore database saja.');
            }
            if ($record['files'] && (config('filesystems.disks.public.driver') !== 'local' || is_link($this->publicDirectory()))) {
                throw new RuntimeException('Restore file memerlukan direktori public lokal tanpa symlink.');
            }

            File::put($this->marker(), $id);
            $this->down();
            $maintenance = true;
            $this->catatan->ubah($id, ['status' => 'backup_pengaman']);
            $safetyPath = $this->cadanganPengaman($id);
            $this->catatan->ubah($id, ['safety_path' => $safetyPath]);
            $this->salinArsip('local', $safetyPath, $work.'/safety.zip');
            $safety = $this->arsip->ekstrak($work.'/safety.zip', $work.'/safety', $connection->getDriverName());

            $this->catatan->ubah($id, ['status' => 'diproses']);
            if ($record['files']) {
                $filesChanged = true;
                $this->gantiFiles($source['files'], $work.'/previous-public');
            }
            $databaseChanged = true;
            $this->database->pulihkan($connection, $source['sql']);
            $this->migrasi();
            $this->bersihkanStatusSementara();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            // User IDs can refer to different accounts in the restored snapshot.
            activity()->causedByAnonymous()->event('backup_direstore')->withProperties([
                'restore_id' => $id, 'disk' => $record['disk'], 'path' => $record['path'],
                'petugas' => $record['actor'], 'petugas_id_sebelum_restore' => $record['actor_id'], 'backup_pengaman' => $safetyPath,
            ])->log('Backup dipulihkan');
            $this->catatan->ubah($id, ['status' => 'berhasil', 'finished_at' => now()->toDateTimeString()]);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            try {
                if ($databaseChanged && $safety !== null) {
                    $this->database->pulihkan($this->connection(), $safety['sql']);
                }
                if ($filesChanged) {
                    $this->kembalikanFiles($work.'/previous-public', $work.'/failed-public');
                }
            } catch (Throwable $rollback) {
                $keepRecovery = true;
                $message .= ' Pemulihan otomatis kondisi sebelumnya gagal: '.$rollback->getMessage().'. Gunakan backup pengaman.';
            }
            $this->gagal($id, $message);
        } finally {
            if ($maintenance && ! $keepRecovery) {
                $this->up();
            }
            if (! $keepRecovery && File::exists($this->marker()) && File::get($this->marker()) === $id) {
                File::delete($this->marker());
            }
            $lock->release();
            try {
                PemantauanBackup::clearBackupDestinationCaches();
            } catch (Throwable $cacheError) {
                report($cacheError);
            }
            // Only delete this validated UUID directory under our temporary root.
            $root = realpath($this->temporaryRoot());
            $resolved = realpath($work);
            if (! $keepRecovery && $root !== false && $resolved !== false && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolved);
            }
        }
    }

    public function gagal(string $id, string $message): void
    {
        $this->catatan->ubah($id, ['status' => 'gagal', 'error' => app(BackupSekolah::class)->pesanKesalahan($message), 'finished_at' => now()->toDateTimeString()]);
    }

    protected function otorisasi(User $user): void
    {
        if (! $user->hasRole(config('filament-shield.super_admin.name'))) {
            throw new RuntimeException('Hanya super admin yang dapat menjalankan restore.');
        }
    }

    protected function validasiLokasi(string $disk, string $path): void
    {
        if (! in_array($disk, PemantauanBackup::getDisks(), true)
            || ! Str::startsWith($path, config()->string('backup.backup.name').'/')
            || ! Str::endsWith($path, '.zip') || Str::contains($path, ['..', '\\', "\0"])
            || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('File backup tidak ditemukan atau lokasinya tidak diizinkan.');
        }
    }

    protected function salinArsip(string $disk, string $path, string $destination): void
    {
        $input = Storage::disk($disk)->readStream($path);
        $output = fopen($destination, 'wb');
        if (! is_resource($input) || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException('Arsip tidak dapat disalin dari penyimpanan.');
        }
        try {
            $written = stream_copy_to_stream($input, $output, 2147483649);
            if ($written === false || $written > 2147483648) {
                throw new RuntimeException('Pengunduhan arsip gagal atau ukuran melebihi 2 GB.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    protected function cadanganPengaman(string $id): string
    {
        $settings = config('backup');
        $settings['backup']['destination']['disks'] = ['local'];
        $settings['backup']['destination']['continue_on_failure'] = false;
        $filename = 'db-and-files-pre-restore-'.now()->format('Y-m-d-H-i-s').'-'.$id.'.zip';
        $config = Config::fromArray($settings);
        BackupJobFactory::createFromConfig($config)->disableSignals()->setFilename($filename)->run();

        return $config->backup->name.'/'.$config->backup->destination->filenamePrefix.$filename;
    }

    protected function connection(): Connection
    {
        return DB::connection();
    }

    protected function migrasi(): void
    {
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Penyesuaian struktur database setelah restore gagal: '.Artisan::output());
        }
    }

    protected function bersihkanStatusSementara(): void
    {
        // Do not replay old queued jobs or revive old login sessions from the dump.
        foreach (['jobs', 'sessions'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    protected function down(): void
    {
        if (Artisan::call('down', ['--retry' => 60]) !== 0) {
            throw new RuntimeException('Maintenance tidak dapat diaktifkan. Restore dibatalkan.');
        }
    }

    protected function up(): void
    {
        Artisan::call('up');
    }

    protected function temporaryRoot(): string
    {
        return storage_path('app/restore-temp');
    }

    protected function marker(): string
    {
        return storage_path('framework/restore-in-progress');
    }

    protected function publicDirectory(): string
    {
        return rtrim(Storage::disk('public')->path(''), '/\\');
    }

    protected function gantiFiles(string $source, string $previous): void
    {
        $target = $this->publicDirectory();
        if (! rename($target, $previous)) {
            throw new RuntimeException('Folder file saat ini tidak dapat disiapkan untuk restore.');
        }
        if (! rename($source, $target)) {
            throw new RuntimeException('Folder file backup tidak dapat dipasang.');
        }
    }

    protected function kembalikanFiles(string $previous, string $failed): void
    {
        $target = $this->publicDirectory();
        if (! is_dir($previous)) {
            return;
        }
        if ((is_dir($target) && ! rename($target, $failed)) || ! rename($previous, $target)) {
            throw new RuntimeException('Folder file sebelumnya tidak dapat dikembalikan.');
        }
    }
}
