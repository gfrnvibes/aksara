<?php

namespace App\Console\Commands;

use App\Models\PresensiGtk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BersihkanFotoPresensiCommand extends Command
{
    protected $signature = 'aksara:bersihkan-foto-presensi {--bulan=6 : Hapus foto presensi yang lebih lama dari jumlah bulan ini}';

    protected $description = 'Menghapus file foto presensi GTK lama dari penyimpanan privat';

    public function handle(): int
    {
        $bulan = max(1, (int) $this->option('bulan'));
        $batas = now()->subMonths($bulan)->toDateString();

        $terhapus = 0;
        PresensiGtk::query()
            ->whereNotNull('foto_path')
            ->whereDate('tanggal', '<', $batas)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$terhapus): void {
                foreach ($rows as $row) {
                    if (filled($row->foto_path)) {
                        Storage::disk('local')->delete($row->foto_path);
                    }
                    $row->update(['foto_path' => null]);
                    $terhapus++;
                }
            });

        $this->info("Berhasil membersihkan {$terhapus} foto presensi sebelum {$batas}.");

        return self::SUCCESS;
    }
}
