<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Semester;
use App\Http\Controllers\Controller;
use App\Models\AnggotaOrganisasi;
use App\Models\NilaiEkstrakurikuler;
use App\Models\NilaiRapor;
use App\Models\PelanggaranSiswa;
use App\Models\PresensiSiswa;
use App\Models\PrestasiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TabunganSiswa;
use App\Models\TagihanSiswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Leger\SusunLeger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiswaPortalController extends Controller
{
    public function beranda(Request $request, SusunLeger $susunLeger): JsonResponse
    {
        $siswa = $this->siswaAktif($request);
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first();
        $semester = Semester::berdasarkanTanggal(now('Asia/Jakarta'));

        $presensiBulanIni = PresensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereBetween('tanggal', [now('Asia/Jakarta')->startOfMonth()->toDateString(), now('Asia/Jakarta')->toDateString()])
            ->get();

        $rekapPresensi = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0];
        foreach ($presensiBulanIni as $row) {
            if (array_key_exists($row->status, $rekapPresensi)) {
                $rekapPresensi[$row->status]++;
            }
        }

        $tagihanList = TagihanSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereNull('dibatalkan_at')
            ->with(['pembayaran' => fn ($q) => $q->whereNull('dibatalkan_at')])
            ->get();

        $totalSisaTagihan = 0;
        foreach ($tagihanList as $tagihan) {
            $totalSisaTagihan += $tagihan->sisa();
        }

        $peringkat = null;
        $rataRata = null;
        if ($tahun !== null && $siswa->kelas !== null) {
            $leger = $susunLeger->untuk($siswa->kelas, $tahun, $semester);
            foreach ($leger->baris as $row) {
                if ($row['siswa']->id === $siswa->id) {
                    $peringkat = $row['peringkat'];
                    $rataRata = $row['rata_rata'];
                    break;
                }
            }
        }

        return response()->json([
            'siswa' => [
                'id' => $siswa->id,
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
                'nama' => $siswa->nama_lengkap,
                'kelas' => $siswa->kelas?->nama,
                'wali_kelas' => $siswa->kelas?->wali_kelas,
            ],
            'tahun_ajaran' => $tahun?->nama,
            'semester' => $semester->getLabel(),
            'ringkasan' => [
                'presensi_bulan_ini' => $rekapPresensi,
                'rata_rata' => $rataRata,
                'peringkat' => $peringkat,
                'total_tagihan_sisa' => $totalSisaTagihan,
                'saldo_tabungan' => TabunganSiswa::saldoUntuk($siswa->id),
                'poin_pelanggaran' => (int) PelanggaranSiswa::query()->where('siswa_id', $siswa->id)->sum('poin'),
                'jumlah_prestasi' => PrestasiSiswa::query()->where('siswa_id', $siswa->id)->count(),
            ],
        ]);
    }

    public function presensi(Request $request): JsonResponse
    {
        $siswa = $this->siswaAktif($request);
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first();
        $semester = Semester::berdasarkanTanggal(now('Asia/Jakarta'));

        $rows = PresensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->when($tahun !== null, fn ($q) => $q->where('tahun_ajaran_id', $tahun->id))
            ->orderByDesc('tanggal')
            ->limit(60)
            ->get();

        $rekap = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0];
        $riwayat = [];
        foreach ($rows as $row) {
            if (array_key_exists($row->status, $rekap)) {
                $rekap[$row->status]++;
            }
            $riwayat[] = [
                'id' => $row->id,
                'tanggal' => $row->tanggal->format('Y-m-d'),
                'status' => $row->status,
                'status_label' => PresensiSiswa::STATUS[$row->status] ?? ucfirst($row->status),
                'terlambat_menit' => $row->terlambat_menit,
                'catatan' => $row->catatan,
            ];
        }

        return response()->json([
            'semester' => $semester->getLabel(),
            'rekap' => $rekap,
            'riwayat' => $riwayat,
        ]);
    }

    public function nilai(Request $request, SusunLeger $susunLeger): JsonResponse
    {
        $siswa = $this->siswaAktif($request);
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first();
        $semesterVal = (int) ($request->query('semester') ?: Semester::berdasarkanTanggal(now('Asia/Jakarta'))->value);
        $semester = Semester::tryFrom($semesterVal) ?? Semester::berdasarkanTanggal(now('Asia/Jakarta'));

        $rapor = $tahun === null ? null : Rapor::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $tahun->id)
            ->where('semester', $semester)
            ->with(['nilaiRapor.mataPelajaran'])
            ->first();

        $peringkat = null;
        $jumlah = 0;
        $rataRata = null;
        $jumlahSiswaKelas = 0;

        if ($tahun !== null && $siswa->kelas !== null) {
            $leger = $susunLeger->untuk($siswa->kelas, $tahun, $semester);
            $jumlahSiswaKelas = count($leger->baris);
            foreach ($leger->baris as $row) {
                if ($row['siswa']->id === $siswa->id) {
                    $peringkat = $row['peringkat'];
                    $jumlah = $row['jumlah'];
                    $rataRata = $row['rata_rata'];
                    break;
                }
            }
        }

        $mapel = [];
        if ($rapor !== null) {
            foreach ($rapor->nilaiRapor->sortBy(fn (NilaiRapor $n): int => $n->mataPelajaran->urutan) as $n) {
                $mapel[] = [
                    'id' => $n->id,
                    'mapel' => $n->mataPelajaran->nama,
                    'kkm' => $n->kkm,
                    'nilai_pengetahuan' => $n->nilai_pengetahuan,
                    'nilai_keterampilan' => $n->nilai_keterampilan,
                    'nilai_akhir' => $n->nilai_akhir,
                    'sikap' => $n->sikap,
                    'capaian_kompetensi' => $n->capaian_kompetensi,
                ];
            }
        }

        return response()->json([
            'semester_aktif' => $semester->value,
            'semester_opsi' => collect(Semester::cases())->mapWithKeys(fn (Semester $s): array => [$s->value => $s->getLabel()])->all(),
            'peringkat' => $peringkat,
            'jumlah_siswa_kelas' => $jumlahSiswaKelas,
            'jumlah' => $jumlah,
            'rata_rata' => $rataRata,
            'catatan_wali_kelas' => $rapor?->catatan,
            'keputusan' => $rapor?->keputusan?->getLabel(),
            'mapel' => $mapel,
        ]);
    }

    public function ekstrakurikuler(Request $request): JsonResponse
    {
        $siswa = $this->siswaAktif($request);
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first();
        $semester = Semester::berdasarkanTanggal(now('Asia/Jakarta'));

        $anggotaRows = AnggotaOrganisasi::query()
            ->with(['ekstrakurikuler.pembina'])
            ->where('siswa_id', $siswa->id)
            ->when($tahun !== null, fn ($q) => $q->where('tahun_ajaran_id', $tahun->id))
            ->get();

        $rapor = $tahun === null ? null : Rapor::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $tahun->id)
            ->where('semester', $semester)
            ->first();

        $nilaiEkskul = $rapor === null
            ? collect()
            : NilaiEkstrakurikuler::query()->where('rapor_id', $rapor->id)->get()->keyBy('ekstrakurikuler_id');

        $items = [];
        foreach ($anggotaRows as $row) {
            $nilai = $nilaiEkskul->get($row->ekstrakurikuler_id);
            $items[] = [
                'id' => $row->id,
                'nama' => $row->ekstrakurikuler->nama,
                'pembina' => $row->ekstrakurikuler->pembina?->nama,
                'jabatan' => $row->jabatan,
                'nilai' => $nilai?->nilai,
                'keterangan' => ($nilai !== null ? $nilai->keterangan : null) ?? $row->catatan,
            ];
        }

        return response()->json(['ekskul' => $items]);
    }

    public function catatan(Request $request): JsonResponse
    {
        $siswa = $this->siswaAktif($request);

        $pelanggaranRows = PelanggaranSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->orderByDesc('tanggal')
            ->get();

        $pelanggaran = [];
        $totalPoin = 0;
        foreach ($pelanggaranRows as $p) {
            $totalPoin += $p->poin;
            $pelanggaran[] = [
                'id' => $p->id,
                'tanggal' => $p->tanggal->format('Y-m-d'),
                'kategori' => $p->kategori,
                'pelanggaran' => $p->pelanggaran,
                'poin' => $p->poin,
                'tindak_lanjut' => $p->tindak_lanjut,
                'status' => $p->status,
            ];
        }

        $prestasiRows = PrestasiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->orderByDesc('tanggal')
            ->get();

        $prestasi = [];
        foreach ($prestasiRows as $pr) {
            $prestasi[] = [
                'id' => $pr->id,
                'tanggal' => $pr->tanggal->format('Y-m-d'),
                'nama_prestasi' => $pr->nama_prestasi,
                'tingkat' => $pr->tingkat,
                'peringkat' => $pr->peringkat,
                'penyelenggara' => $pr->penyelenggara,
                'keterangan' => $pr->keterangan,
            ];
        }

        return response()->json([
            'total_poin_pelanggaran' => $totalPoin,
            'pelanggaran' => $pelanggaran,
            'prestasi' => $prestasi,
        ]);
    }

    public function keuangan(Request $request): JsonResponse
    {
        $siswa = $this->siswaAktif($request);

        $tagihanRows = TagihanSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereNull('dibatalkan_at')
            ->with(['pembayaran' => fn ($q) => $q->whereNull('dibatalkan_at')])
            ->orderByDesc('jatuh_tempo')
            ->get();

        $tagihan = [];
        $totalSisa = 0;
        foreach ($tagihanRows as $t) {
            $terbayar = $t->terbayar();
            $sisa = $t->sisa();
            $totalSisa += $sisa;
            $tagihan[] = [
                'id' => $t->id,
                'nama' => $t->nama,
                'periode' => $t->periode,
                'nominal' => $t->nominal,
                'terbayar' => $terbayar,
                'sisa' => $sisa,
                'status' => $t->status(),
                'jatuh_tempo' => $t->jatuh_tempo->format('Y-m-d'),
            ];
        }

        $tabunganRows = TabunganSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $tabungan = [];
        foreach ($tabunganRows as $tb) {
            $tabungan[] = [
                'id' => $tb->id,
                'tanggal' => $tb->tanggal->format('Y-m-d'),
                'jenis' => $tb->jenis,
                'nominal' => $tb->nominal,
                'keterangan' => $tb->keterangan,
            ];
        }

        return response()->json([
            'total_sisa_tagihan' => $totalSisa,
            'tagihan' => $tagihan,
            'saldo_tabungan' => TabunganSiswa::saldoUntuk($siswa->id),
            'tabungan' => $tabungan,
        ]);
    }

    protected function siswaAktif(Request $request): Siswa
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->siswa !== null, 403);

        $user->siswa->loadMissing('kelas');

        return $user->siswa;
    }
}
