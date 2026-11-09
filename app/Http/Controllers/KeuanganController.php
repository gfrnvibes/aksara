<?php

namespace App\Http\Controllers;

use App\Models\GajiGuru;
use App\Models\TransaksiKeuangan;
use App\Services\Keuangan\LaporanKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KeuanganController extends Controller
{
    public function kuitansi(TransaksiKeuangan $transaksi): View
    {
        PencatatanKeuangan::izinkan();

        return view('cetak.kuitansi', ['transaksi' => $transaksi->load(['tagihanSiswa.siswa', 'pencatat', 'pembatal'])]);
    }

    public function slipGaji(GajiGuru $gaji): View
    {
        PencatatanKeuangan::izinkan();
        $gaji->load(['pencatat', 'transaksi.pencatat', 'transaksi.pembatal']);

        return view('cetak.slip-gaji', ['gaji' => $gaji, 'pembayaran' => $gaji->transaksi->firstWhere('dibatalkan_at', null)]);
    }

    public function laporan(Request $request): StreamedResponse
    {
        PencatatanKeuangan::izinkan();
        $data = $request->validate(['mulai' => ['required', 'date_format:Y-m-d'], 'selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:mulai']]);
        $laporan = app(LaporanKeuangan::class)->susun($data['mulai'], $data['selesai']);

        return response()->streamDownload(function () use ($data, $laporan): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }
            fwrite($file, "\xEF\xBB\xBF");
            $tulis = static function (array $baris) use ($file): void {
                // Spreadsheet applications must not execute user-supplied text as a formula.
                fputcsv($file, array_map(static fn ($nilai) => is_string($nilai) && preg_match('/^[\s]*[=+@-]/u', $nilai) ? "'".$nilai : $nilai, $baris), ';', '"', '');
            };
            $tulis(['Laporan keuangan', $data['mulai'], $data['selesai']]);
            foreach (['saldoAwal' => 'Saldo awal', 'pemasukan' => 'Pemasukan sah', 'pengeluaran' => 'Pengeluaran sah', 'saldoAkhir' => 'Saldo akhir'] as $key => $label) {
                $tulis([$label, $laporan[$key]]);
            }
            $tulis([]);
            $tulis(['Nomor', 'Tanggal', 'Jenis', 'Kategori', 'Uraian', 'Nominal (Rp)', 'Metode', 'Referensi', 'Pencatat', 'Waktu catat', 'Status', 'Alasan pembatalan', 'Pembatal', 'Waktu pembatalan']);
            foreach (TransaksiKeuangan::query()->with(['pencatat', 'pembatal'])->whereDate('tanggal', '>=', $data['mulai'])->whereDate('tanggal', '<=', $data['selesai'])->orderBy('tanggal')->orderBy('id')->lazy() as $t) {
                $tulis([$t->nomor, $t->tanggal->toDateString(), $t->jenis, $t->kategori, $t->uraian, $t->nominal, $t->metode, $t->referensi, $t->pencatat->name, (string) $t->getAttribute('created_at'), $t->dibatalkan_at ? 'Dibatalkan' : 'Sah', $t->alasan_pembatalan, $t->pembatal?->name, (string) $t->dibatalkan_at]);
            }
            fclose($file);
        }, 'laporan-keuangan-'.$data['mulai'].'-'.$data['selesai'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
