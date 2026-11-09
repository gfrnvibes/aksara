<?php

namespace App\Jobs;

use App\Services\BackupSekolah;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BuatBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public string $batch) {}

    public function handle(BackupSekolah $backup): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }
        $backup->jalankan($this->batch);
    }

    public function failed(?Throwable $exception): void
    {
        app(BackupSekolah::class)->gagal($this->batch, $exception?->getMessage() ?? 'Proses backup terhenti.');
    }
}
