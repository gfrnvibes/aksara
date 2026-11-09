<?php

namespace App\Services;

use App\Models\RiwayatBackup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Throwable;

class BackupSekolah
{
    public function mulai(BackupType $jenis, string $sumber, ?int $userId = null): string
    {
        $batch = (string) Str::uuid();
        $disks = FilamentSpatieLaravelBackup::getDisks();
        if ($disks === []) {
            throw new RuntimeException('BACKUP_DISKS belum berisi tujuan penyimpanan.');
        }

        DB::transaction(function () use ($batch, $disks, $jenis, $sumber, $userId): void {
            foreach ($disks as $disk) {
                RiwayatBackup::query()->create([
                    'batch' => $batch, 'disk' => $disk, 'jenis' => $jenis->value,
                    'sumber' => $sumber, 'user_id' => $userId, 'status' => 'menunggu',
                ]);
            }
        });

        return $batch;
    }

    public function jalankan(string $batch): void
    {
        if (File::exists(storage_path('framework/restore-in-progress'))) {
            $this->gagal($batch, 'Restore sedang berjalan. Backup dibatalkan.');

            return;
        }
        // Spatie uses a shared temporary directory. Never run two dumps concurrently.
        $lock = Cache::lock('aksara:backup', 3600);
        if (! $lock->get()) {
            $this->gagal($batch, 'Backup lain masih berjalan. Coba kembali setelah proses tersebut selesai.');

            return;
        }

        try {
            foreach (RiwayatBackup::query()->where('batch', $batch)->where('status', 'menunggu')->get() as $riwayat) {
                $riwayat->update(['status' => 'diproses', 'mulai_at' => now()]);

                try {
                    $this->simpan($riwayat);
                } catch (Throwable $exception) {
                    $riwayat->update([
                        'status' => 'gagal', 'selesai_at' => now(),
                        'kesalahan' => $this->pesanKesalahan($exception->getMessage()),
                    ]);
                }
            }
        } finally {
            $lock->release();
            FilamentSpatieLaravelBackup::clearBackupDestinationCaches();
        }
    }

    protected function simpan(RiwayatBackup $riwayat): void
    {
        // Build a separate destination config: a broken adapter must not prevent
        // the other destinations from even starting (including PHP Errors).
        $settings = config('backup');
        $settings['backup']['destination']['disks'] = [$riwayat->disk];
        $settings['backup']['destination']['continue_on_failure'] = false;
        $config = Config::fromArray($settings);
        $filename = $riwayat->jenis.'-'.now()->format('Y-m-d-H-i-s').'-'.$riwayat->batch.'.zip';
        $path = $config->backup->name.'/'.$config->backup->destination->filenamePrefix.$filename;

        $job = BackupJobFactory::createFromConfig($config)->disableSignals()->setFilename($filename);
        if ($riwayat->jenis === BackupType::ONLY_DATABASE->value) {
            $job->dontBackupFilesystem();
        }
        $job->run();

        $riwayat->update([
            'status' => 'berhasil', 'path' => $path,
            'ukuran' => Storage::disk($riwayat->disk)->size($path),
            'selesai_at' => now(), 'kesalahan' => null,
        ]);
    }

    public function gagal(string $batch, string $pesan): void
    {
        RiwayatBackup::query()->where('batch', $batch)->whereIn('status', ['menunggu', 'diproses'])
            ->update(['status' => 'gagal', 'kesalahan' => $this->pesanKesalahan($pesan), 'selesai_at' => now()]);
    }

    public function pesanKesalahan(string $pesan): string
    {
        $secrets = [config('backup.backup.password'), config('database.connections.'.config('database.default').'.password')];
        foreach (config('filesystems.disks', []) as $disk) {
            foreach (['key', 'secret', 'password', 'clientSecret', 'refreshToken'] as $key) {
                $secrets[] = $disk[$key] ?? null;
            }
        }
        foreach (array_filter($secrets) as $secret) {
            $pesan = str_replace($secret, '[disembunyikan]', $pesan);
        }

        return Str::limit($pesan, 4000);
    }
}
