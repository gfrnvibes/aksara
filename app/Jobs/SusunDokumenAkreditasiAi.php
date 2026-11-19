<?php

namespace App\Jobs;

use App\Models\AkreditasiDokumen;
use App\Services\Akreditasi\PenyusunDokumenAkreditasiAi;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafts a single accreditation document (DW or DKA Butir) with Gemini AI in the background queue,
 * preventing browser timeouts when generating all 20 documents at once.
 */
class SusunDokumenAkreditasiAi implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(public int $dokumenId) {}

    public function handle(PenyusunDokumenAkreditasiAi $penyusun): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $dokumen = AkreditasiDokumen::query()->with('tahunAjaran')->find($this->dokumenId);
        if ($dokumen === null) {
            return;
        }

        $penyusun->susun($dokumen);
    }
}
