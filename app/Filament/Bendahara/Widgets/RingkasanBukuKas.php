<?php

namespace App\Filament\Bendahara\Widgets;

use App\Filament\Bendahara\Pages\HalamanKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class RingkasanBukuKas extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Ringkasan Buku Kas';

    protected ?string $description = 'Mengikuti filter dan pencarian; transaksi batal tidak dihitung. Klik kartu untuk menyaring transaksi sah.';

    /** @var array<string, int> */
    #[Reactive]
    public array $ringkasan = [];

    public static function canView(): bool
    {
        return HalamanKeuangan::canAccess();
    }

    protected function getStats(): array
    {
        PencatatanKeuangan::izinkan();
        $masuk = $this->ringkasan['masuk'] ?? 0;
        $keluar = $this->ringkasan['keluar'] ?? 0;

        return [
            $this->kartu('Pemasukan', $masuk, Heroicon::OutlinedArrowDownLeft, 'success', 'Lihat pemasukan sah', 'pemasukan'),
            $this->kartu('Pengeluaran', $keluar, Heroicon::OutlinedArrowUpRight, 'gray', 'Lihat pengeluaran sah', 'pengeluaran'),
            $this->kartu('Selisih Masuk - Keluar', $masuk - $keluar, Heroicon::OutlinedScale, $masuk < $keluar ? 'danger' : 'primary', 'Lihat semua transaksi sah', ''),
        ];
    }

    private function kartu(string $label, int $nominal, Heroicon $icon, string $color, string $description, string $jenis): Stat
    {
        return Stat::make($label, 'Rp '.number_format($nominal, 0, ',', '.'))->icon($icon)->color($color)->description($description)->descriptionIcon(Heroicon::OutlinedArrowRight)
            ->extraAttributes([
                'class' => 'keuangan-stat cursor-pointer '.match ($color) {
                    'success' => 'keuangan-stat-masuk', 'danger' => 'keuangan-stat-sisa', 'gray' => 'keuangan-stat-keluar', default => 'keuangan-stat-saldo'
                },
                'x-data' => '{}', 'role' => 'button', 'tabindex' => '0', 'aria-label' => $description,
                'wire:click' => "\$dispatch('filter-ringkasan-kas', { jenis: '{$jenis}' })",
                'x-on:keydown.enter.prevent' => '$el.click()', 'x-on:keydown.space.prevent' => '$el.click()',
            ]);
    }
}
