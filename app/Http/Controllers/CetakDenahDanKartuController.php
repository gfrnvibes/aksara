<?php

namespace App\Http\Controllers;

use App\Enums\Hari;
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

    public function daftarHadir(Request $request, KegiatanUjian $kegiatan): View
    {
        Gate::authorize('view', $kegiatan);

        $kegiatan->load(['tahunAjaran', 'penandaTangan', 'ketuaPanitia', 'kopSurat']);
        $ruangId = $request->integer('ruang_id') ?: null;

        $ruangList = $kegiatan->ruang()
            ->when($ruangId !== null, fn ($q) => $q->whereKey($ruangId))
            ->with(['penempatan' => fn ($q) => $q->with(['siswa', 'kelas'])->orderBy('nomor_kursi')->orderBy('nomor_peserta')])
            ->get();

        return view('cetak.daftar-hadir-ujian', [
            'kegiatan' => $kegiatan,
            'ruangList' => $ruangList,
            'daftarTanggal' => $this->daftarTanggalUjian($kegiatan),
        ]);
    }

    public function semuanya(Request $request, KegiatanUjian $kegiatan): View
    {
        Gate::authorize('view', $kegiatan);

        $kegiatan->load(['tahunAjaran', 'penandaTangan', 'ketuaPanitia', 'kopSurat']);
        $ruangId = $request->integer('ruang_id') ?: null;

        $ruangList = $kegiatan->ruang()
            ->when($ruangId !== null, fn ($q) => $q->whereKey($ruangId))
            ->with(['penempatan' => fn ($q) => $q->with(['siswa', 'kelas'])->orderBy('urutan_posisi')->orderBy('nomor_kursi')])
            ->get();

        $peserta = $kegiatan->penempatan()
            ->with(['siswa', 'kelas', 'ruangUjian'])
            ->when($ruangId !== null, fn ($q) => $q->where('ruang_ujian_id', $ruangId))
            ->orderBy('ruang_ujian_id')
            ->orderBy('nomor_kursi')
            ->orderBy('nomor_peserta')
            ->get();

        $kop = $kegiatan->kopSurat !== null
            ? $kegiatan->kopSurat->konfigurasi
            : PengaturanKop::untuk('surat_keterangan');

        return view('cetak.paket-ujian', [
            'kegiatan' => $kegiatan,
            'ruangList' => $ruangList,
            'peserta' => $peserta,
            'daftarTanggal' => $this->daftarTanggalUjian($kegiatan),
            'kop' => $kop,
        ]);
    }

    /**
     * Build exam date columns from tanggal_mulai to tanggal_selesai, excluding Sundays.
     *
     * @return list<array{hari: string, tanggal: string}>
     */
    protected function daftarTanggalUjian(KegiatanUjian $kegiatan): array
    {
        if ($kegiatan->tanggal_mulai === null || $kegiatan->tanggal_selesai === null) {
            return [];
        }

        $mulai = $kegiatan->tanggal_mulai->copy()->startOfDay();
        $selesai = $kegiatan->tanggal_selesai->copy()->startOfDay();
        if ($selesai->lt($mulai)) {
            return [];
        }

        $daftar = [];
        for ($tanggal = $mulai; $tanggal->lte($selesai); $tanggal = $tanggal->addDay()) {
            if ($tanggal->isSunday()) {
                continue;
            }

            $hari = Hari::dariTanggal($tanggal)?->getLabel() ?? $tanggal->translatedFormat('l');
            $daftar[] = [
                'hari' => $hari,
                'tanggal' => $tanggal->format('d/m/Y'),
            ];
        }

        return $daftar;
    }
}
