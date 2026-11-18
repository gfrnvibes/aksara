<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjian;
use App\Services\Surat\PengaturanKop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CetakDenahDanKartuController extends Controller
{
    public function kartu(Request $request, KegiatanUjian $kegiatan): View
    {
        Gate::authorize('view', $kegiatan);

        $kegiatan->load(['tahunAjaran', 'penandaTangan', 'ketuaPanitia', 'kopSurat']);

        $ruangId = $request->integer('ruang_id') ?: null;
        $kelasId = $request->integer('kelas_id') ?: null;

        $peserta = $kegiatan->penempatan()
            ->with(['siswa', 'kelas', 'ruangUjian'])
            ->when($ruangId !== null, fn ($q) => $q->where('ruang_ujian_id', $ruangId))
            ->when($kelasId !== null, fn ($q) => $q->where('kelas_id', $kelasId))
            ->orderBy('ruang_ujian_id')
            ->orderBy('nomor_kursi')
            ->orderBy('nomor_peserta')
            ->get();

        $kop = $kegiatan->kopSurat !== null
            ? $kegiatan->kopSurat->konfigurasi
            : PengaturanKop::untuk('surat_keterangan');

        return view('cetak.kartu-ujian', [
            'kegiatan' => $kegiatan,
            'peserta' => $peserta,
            'kop' => $kop,
        ]);
    }

    public function denah(Request $request, KegiatanUjian $kegiatan): View
    {
        Gate::authorize('view', $kegiatan);

        $kegiatan->load(['tahunAjaran', 'penandaTangan', 'ketuaPanitia', 'kopSurat']);

        $ruangId = $request->integer('ruang_id') ?: null;

        $ruangList = $kegiatan->ruang()
            ->when($ruangId !== null, fn ($q) => $q->whereKey($ruangId))
            ->with(['penempatan' => fn ($q) => $q->with(['siswa', 'kelas'])->orderBy('urutan_posisi')->orderBy('nomor_kursi')])
            ->get();

        $kop = $kegiatan->kopSurat !== null
            ? $kegiatan->kopSurat->konfigurasi
            : PengaturanKop::untuk('surat_keterangan');

        return view('cetak.denah-ujian', [
            'kegiatan' => $kegiatan,
            'ruangList' => $ruangList,
            'kop' => $kop,
        ]);
    }
}
