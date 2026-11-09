<?php

namespace App\Jobs;

use App\Services\RestoreBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PulihkanBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public string $id) {}

    public function handle(RestoreBackup $restore): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }
        $restore->jalankan($this->id);
    }

    public function failed(?Throwable $exception): void
    {
        app(RestoreBackup::class)->gagal($this->id, $exception?->getMessage() ?? 'Proses restore terhenti. Periksa backup pengaman dan status maintenance sebelum mencoba kembali.');
    }
}
