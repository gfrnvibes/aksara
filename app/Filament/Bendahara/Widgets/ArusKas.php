<?php

namespace App\Filament\Bendahara\Widgets;

use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\Keuangan\PencatatanKeuangan;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;

class ArusKas extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Grafik Arus Kas';

    protected ?string $description = 'Pemasukan hijau dan pengeluaran abu-abu per tanggal. Arahkan kursor atau ketuk batang untuk melihat nominal.';

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    #[Locked]
    public string $mulai = '';

    #[Locked]
    public string $selesai = '';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->bolehKelolaKeuangan();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        PencatatanKeuangan::izinkan();
        $baris = TransaksiKeuangan::query()->whereNull('dibatalkan_at')
            ->whereDate('tanggal', '>=', $this->mulai)->whereDate('tanggal', '<=', $this->selesai)
            ->selectRaw("date(tanggal) as hari, sum(case when jenis = 'pemasukan' then nominal else 0 end) as masuk, sum(case when jenis = 'pengeluaran' then nominal else 0 end) as keluar")
            ->groupByRaw('date(tanggal)')->orderBy('hari')->get();
        if ($baris->isEmpty()) {
            return [];
        }

        return [
            'labels' => $baris->map(fn (TransaksiKeuangan $t): string => (string) $t->getAttribute('hari'))->all(),
            'datasets' => [
                ['label' => 'Pemasukan (Rp)', 'data' => $baris->map(fn (TransaksiKeuangan $t): int => (int) $t->getAttribute('masuk'))->all(), 'backgroundColor' => '#10b981', 'borderRadius' => 4],
                ['label' => 'Pengeluaran (Rp)', 'data' => $baris->map(fn (TransaksiKeuangan $t): int => (int) $t->getAttribute('keluar'))->all(), 'backgroundColor' => '#94a3b8', 'borderRadius' => 4],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true]], 'plugins' => ['legend' => ['position' => 'bottom']]];
    }
}
