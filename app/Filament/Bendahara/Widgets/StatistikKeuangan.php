<?php

namespace App\Filament\Bendahara\Widgets;

use App\Filament\Bendahara\Pages\BukuKas;
use App\Filament\Bendahara\Pages\TagihanSiswa;
use App\Models\User;
use App\Services\Keuangan\LaporanKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Locked;

class StatistikKeuangan extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    #[Locked]
    public string $mulai = '';

    #[Locked]
    public string $selesai = '';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->bolehKelolaKeuangan();
    }

    protected function getStats(): array
    {
        PencatatanKeuangan::izinkan();
        $laporan = app(LaporanKeuangan::class)->susun($this->mulai, $this->selesai);
        $rupiah = fn (string $key): string => 'Rp '.number_format($laporan[$key], 0, ',', '.');
        $kas = fn (?string $jenis = null): string => BukuKas::getUrl(['filters' => [
            'periode' => ['mulai' => $this->mulai, 'selesai' => $this->selesai],
            'status' => ['value' => 'sah'], 'jenis' => ['value' => $jenis],
        ]]);

        return [
            Stat::make('Saldo Awal', $rupiah('saldoAwal'))->icon(Heroicon::OutlinedWallet)->description('Saldo sebelum periode terpilih')->color('primary')->extraAttributes(['class' => 'keuangan-stat keuangan-stat-saldo']),
            Stat::make('Pemasukan', $rupiah('pemasukan'))->icon(Heroicon::OutlinedArrowDownLeft)->description('Lihat pemasukan periode ini')->descriptionIcon(Heroicon::OutlinedArrowRight)->color('success')->url($kas('pemasukan'))->extraAttributes(['class' => 'keuangan-stat keuangan-stat-masuk']),
            Stat::make('Pengeluaran', $rupiah('pengeluaran'))->icon(Heroicon::OutlinedArrowUpRight)->description('Lihat pengeluaran periode ini')->descriptionIcon(Heroicon::OutlinedArrowRight)->color('gray')->url($kas('pengeluaran'))->extraAttributes(['class' => 'keuangan-stat keuangan-stat-keluar']),
            Stat::make('Saldo Akhir', $rupiah('saldoAkhir'))->icon(Heroicon::OutlinedBanknotes)->description('Saldo awal + masuk - keluar')->color($laporan['saldoAkhir'] < 0 ? 'danger' : 'primary')->url($kas())->extraAttributes(['class' => 'keuangan-stat '.($laporan['saldoAkhir'] < 0 ? 'keuangan-stat-sisa' : 'keuangan-stat-saldo')]),
            Stat::make('Sisa Tagihan', $rupiah('piutang'))->icon(Heroicon::OutlinedExclamationCircle)->description('Belum lunas, semua periode')->descriptionIcon(Heroicon::OutlinedArrowRight)->color('danger')->url(TagihanSiswa::getUrl())->extraAttributes(['class' => 'keuangan-stat keuangan-stat-sisa']),
            Stat::make('Transaksi Dibatalkan', $laporan['pembatalan'])->icon(Heroicon::OutlinedArrowUturnLeft)->description('Tidak dihitung dalam saldo')->color('gray')->extraAttributes(['class' => 'keuangan-stat keuangan-stat-keluar']),
        ];
    }
}
