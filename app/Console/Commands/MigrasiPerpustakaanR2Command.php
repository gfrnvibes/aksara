<?php

namespace App\Console\Commands;

use App\Models\BukuPerpustakaan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrasiPerpustakaanR2Command extends Command
{
    protected $signature = 'aksara:migrasi-perpustakaan-r2 {--hapus-lokal : Hapus file dari disk lokal setelah berhasil disalin}';

    protected $description = 'Salin file PDF dan sampul buku perpustakaan yang ada di disk lokal/public ke disk perpustakaan (R2)';

    public function handle(): int
    {
        $targetPdfDisk = BukuPerpustakaan::diskName();
        $targetSampulDisk = BukuPerpustakaan::sampulDiskName();

        if (! BukuPerpustakaan::adalahCloudDisk($targetPdfDisk)) {
            $this->error("Disk perpustakaan aktif saat ini adalah '{$targetPdfDisk}' (bukan driver s3/r2). Atur PERPUSTAKAAN_DISK=r2 atau ARSIP_DISK=r2 terlebih dahulu.");

            return self::FAILURE;
        }

        $hapusLokal = (bool) $this->option('hapus-lokal');
        $dipindah = 0;

        foreach (BukuPerpustakaan::query()->orderBy('id')->cursor() as $buku) {
            if (filled($buku->file_pdf)
                && ! Storage::disk($targetPdfDisk)->exists($buku->file_pdf)
                && Storage::disk('local')->exists($buku->file_pdf)
            ) {
                $stream = Storage::disk('local')->readStream($buku->file_pdf);
                if ($stream !== null) {
                    Storage::disk($targetPdfDisk)->put($buku->file_pdf, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $dipindah++;
                    if ($hapusLokal) {
                        Storage::disk('local')->delete($buku->file_pdf);
                    }
                }
            }

            if (filled($buku->sampul)
                && ! str_starts_with($buku->sampul, 'http')
                && ! Storage::disk($targetSampulDisk)->exists($buku->sampul)
                && Storage::disk('public')->exists($buku->sampul)
            ) {
                $stream = Storage::disk('public')->readStream($buku->sampul);
                if ($stream !== null) {
                    Storage::disk($targetSampulDisk)->put($buku->sampul, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $dipindah++;
                    if ($hapusLokal) {
                        Storage::disk('public')->delete($buku->sampul);
                    }
                }
            }
        }

        $this->info("Migrasi selesai. {$dipindah} berkas disalin ke disk '{$targetPdfDisk}'.");

        return self::SUCCESS;
    }
}
