<?php

namespace App\Console\Commands;

use App\Models\RiwayatBackup;
use App\Services\BackupSekolah;
use Illuminate\Console\Command;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;

class BackupSekolahCommand extends Command
{
    protected $signature = 'aksara:backup {--only-db : Cadangkan database saja}';

    protected $description = 'Backup sekolah ke setiap tujuan dan catat hasil masing-masing tujuan';

    public function handle(BackupSekolah $backup): int
    {
        $jenis = $this->option('only-db') ? BackupType::ONLY_DATABASE : BackupType::DATABASE_AND_FILES;
        $batch = $backup->mulai($jenis, 'terjadwal');
        $backup->jalankan($batch);
        $gagal = RiwayatBackup::query()->where('batch', $batch)->where('status', '!=', 'berhasil')->count();
        $this->info("Proses backup selesai; {$gagal} tujuan gagal. Lihat riwayat pada halaman Backup Data.");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
