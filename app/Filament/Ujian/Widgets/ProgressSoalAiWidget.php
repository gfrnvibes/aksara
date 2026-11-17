<?php

namespace App\Filament\Ujian\Widgets;

use App\Jobs\BuatSoalAi as JobBuatSoalAi;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;

class ProgressSoalAiWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.ujian.widgets.progress-soal-ai';

    protected int|string|array $columnSpan = 'full';

    #[Locked]
    public int $mapel = 0;

    public static function canView(): bool
    {
        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        $mapel = request()->route('mapel');
        if ($mapel !== null) {
            return Cache::has(static::kunciAktif((int) $userId, (int) $mapel));
        }

        return true;
    }

    public static function kunciAktif(int $userId, int $mapelId): string
    {
        return "soal-ai:user:{$userId}:mapel:{$mapelId}:aktif";
    }

    public function sembunyikan(): void
    {
        $userId = Auth::id();
        if ($userId !== null) {
            Cache::forget(static::kunciAktif((int) $userId, $this->mapel));
        }
    }

    public function cekProgres(): void
    {
        $info = $this->infoAktif();
        if ($info === null) {
            return;
        }

        $batch = Bus::findBatch($info['batch_id']);
        if ($batch === null) {
            $this->sembunyikan();

            return;
        }

        if (! $batch->finished()) {
            return;
        }

        $this->selesaikan($batch, $info);
    }

    /**
     * @return array{
     *     label: string,
     *     total: int,
     *     selesai: int,
     *     gagal: int,
     *     persen: int,
     *     estimasi: string,
     *     antrean_macet: bool
     * }|null
     */
    public function getStatus(): ?array
    {
        $info = $this->infoAktif();
        if ($info === null) {
            return null;
        }

        $batch = Bus::findBatch($info['batch_id']);
        if ($batch === null) {
            $this->sembunyikan();

            return null;
        }

        if ($batch->finished()) {
            $this->selesaikan($batch, $info);

            return null;
        }

        $total = max(1, $batch->totalJobs);
        $selesai = $batch->processedJobs();
        $gagal = $batch->failedJobs;
        $persen = (int) min(99, round(($selesai / $total) * 100));
        $berjalanDetik = max(1, time() - $info['started_at']);
        $macet = $selesai === 0 && $berjalanDetik >= 15;

        if ($selesai > 0) {
            $sisaDetik = (int) ceil(($berjalanDetik / $selesai) * max(0, $total - $selesai));
            $estimasi = 'Perkiraan sisa waktu: ~'.$this->formatDurasi($sisaDetik);
        } elseif ($macet) {
            $estimasi = 'Antrean belum bergerak — pastikan worker (php artisan queue:work) sedang berjalan.';
        } else {
            $estimasi = 'Menyiapkan proses AI...';
        }

        return [
            'label' => $info['label'],
            'total' => $total,
            'selesai' => $selesai,
            'gagal' => $gagal,
            'persen' => $persen,
            'estimasi' => $estimasi,
            'antrean_macet' => $macet,
        ];
    }

    /**
     * @return array{batch_id: string, label: string, started_at: int}|null
     */
    private function infoAktif(): ?array
    {
        $userId = Auth::id();
        if ($userId === null || $this->mapel === 0) {
            return null;
        }

        $data = Cache::get(static::kunciAktif((int) $userId, $this->mapel));

        if (! is_array($data) || empty($data['batch_id'])) {
            return null;
        }

        return [
            'batch_id' => (string) $data['batch_id'],
            'label' => (string) ($data['label'] ?? 'Bank Soal'),
            'started_at' => (int) ($data['started_at'] ?? time()),
        ];
    }

    /**
     * @param  array{batch_id: string, label: string, started_at: int}  $info
     */
    private function selesaikan(Batch $batch, array $info): void
    {
        $this->sembunyikan();

        $jumlah = (int) Cache::get(JobBuatSoalAi::kunciJumlah($batch->id), 0);
        $gagalMateri = (array) Cache::get(JobBuatSoalAi::kunciGagal($batch->id), []);
        $errors = (array) Cache::pull(JobBuatSoalAi::kunciError($batch->id), []);

        if ($batch->failedJobs > 0) {
            $detail = $errors !== []
                ? implode(' | ', array_slice($errors, 0, 2))
                : 'Gagal pada: '.implode(', ', $gagalMateri).'. Periksa storage/logs/laravel.log untuk detailnya.';

            Notification::make()
                ->danger()
                ->title("Terjadi kesalahan saat membuat soal AI ({$batch->failedJobs} baris gagal)")
                ->body($detail)
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title("{$jumlah} draf soal selesai dibuat!")
                ->body("Soal untuk {$info['label']} sudah masuk sebagai draf.")
                ->send();
        }

        $this->dispatch('soal-ai-selesai');
    }

    private function formatDurasi(int $detik): string
    {
        if ($detik < 60) {
            return "{$detik} detik";
        }

        $menit = intdiv($detik, 60);
        $sisa = $detik % 60;

        return $sisa > 0 ? "{$menit} menit {$sisa} detik" : "{$menit} menit";
    }
}
