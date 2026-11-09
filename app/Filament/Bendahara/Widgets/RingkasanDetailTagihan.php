<?php

namespace App\Filament\Bendahara\Widgets;

use App\Filament\Bendahara\Pages\HalamanKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class RingkasanDetailTagihan extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Ringkasan Tagihan';

    protected ?string $description = 'Mengikuti filter dan pencarian. Klik kartu untuk menelusuri penerima atau status tagihan.';

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
        $rupiah = fn (string $key): string => 'Rp '.number_format($this->ringkasan[$key] ?? 0, 0, ',', '.');

        return [
            $this->kartu('Siswa Ditagih', (string) ($this->ringkasan['jumlah_siswa'] ?? 0), Heroicon::OutlinedUsers, 'primary', 'Semua penerima, termasuk riwayat batal', ''),
            $this->kartu('Total Tagihan Aktif', $rupiah('total_nominal'), Heroicon::OutlinedClipboardDocumentList, 'primary', 'Lihat semua tagihan aktif', 'berlaku'),
            $this->kartu('Total Terbayar', $rupiah('total_terbayar'), Heroicon::OutlinedCheckCircle, 'success', 'Lihat pembayaran, termasuk cicilan', 'terbayar'),
            $this->kartu('Total Sisa Tagihan', $rupiah('total_sisa'), Heroicon::OutlinedExclamationCircle, 'danger', 'Lihat tagihan yang belum lunas', 'aktif'),
        ];
    }

    private function kartu(string $label, string $nilai, Heroicon $icon, string $color, string $description, string $status): Stat
    {
        return Stat::make($label, $nilai)->icon($icon)->color($color)->description($description)->descriptionIcon(Heroicon::OutlinedArrowRight)
            ->extraAttributes([
                'class' => 'keuangan-stat cursor-pointer '.match ($color) {
                    'success' => 'keuangan-stat-masuk', 'danger' => 'keuangan-stat-sisa', default => 'keuangan-stat-saldo'
                },
                'x-data' => '{}', 'role' => 'button', 'tabindex' => '0', 'aria-label' => $description,
                'wire:click' => "\$dispatch('filter-ringkasan-tagihan', { status: '{$status}' })",
                'x-on:keydown.enter.prevent' => '$el.click()', 'x-on:keydown.space.prevent' => '$el.click()',
            ]);
    }
}
