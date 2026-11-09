<?php

namespace App\Services\Keuangan;

use App\Models\TagihanSiswa;
use App\Models\TransaksiKeuangan;

class LaporanKeuangan
{
    /** @return array<string, mixed> */
    public function susun(string $mulai, string $selesai): array
    {
        $sah = TransaksiKeuangan::query()->whereNull('dibatalkan_at');
        $saldoAwal = (int) (clone $sah)->whereDate('tanggal', '<', $mulai)
            ->selectRaw("coalesce(sum(case when jenis = 'pemasukan' then nominal else -nominal end), 0) as saldo")->value('saldo');
        $periode = (clone $sah)->whereDate('tanggal', '>=', $mulai)->whereDate('tanggal', '<=', $selesai);
        $masuk = (int) (clone $periode)->where('jenis', 'pemasukan')->sum('nominal');
        $keluar = (int) (clone $periode)->where('jenis', 'pengeluaran')->sum('nominal');
        $tagihan = TagihanSiswa::query()->whereNull('dibatalkan_at');
        $dibayar = TransaksiKeuangan::query()->whereNotNull('tagihan_siswa_id')->whereNull('dibatalkan_at')->sum('nominal');

        return [
            'saldoAwal' => $saldoAwal, 'pemasukan' => $masuk, 'pengeluaran' => $keluar, 'saldoAkhir' => $saldoAwal + $masuk - $keluar,
            'piutang' => (int) $tagihan->sum('nominal') - (int) $dibayar,
            'rincian' => (clone $periode)->select('jenis', 'kategori')->selectRaw('sum(nominal) as total')->groupBy('jenis', 'kategori')->orderBy('jenis')->orderBy('kategori')->get(),
            'pembatalan' => TransaksiKeuangan::query()->whereDate('tanggal', '>=', $mulai)->whereDate('tanggal', '<=', $selesai)->whereNotNull('dibatalkan_at')->count(),
        ];
    }
}
