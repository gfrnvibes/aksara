<?php

namespace App\Services\Akreditasi;

use App\Enums\JenisGtk;
use App\Enums\StatusSiswa;
use App\Models\AlurTujuanPembelajaran;
use App\Models\BukuPerpustakaan;
use App\Models\EdmEvaluasi;
use App\Models\Ekstrakurikuler;
use App\Models\GaleriKegiatan;
use App\Models\Gtk;
use App\Models\InventarisMedia;
use App\Models\JurnalMengajar;
use App\Models\KegiatanIbadahSiswa;
use App\Models\KegiatanRemedial;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\PelanggaranSiswa;
use App\Models\PelatihanGtk;
use App\Models\PembagianMengajar;
use App\Models\PerangkatAjar;
use App\Models\PresensiGtk;
use App\Models\PrestasiSiswa;
use App\Models\ProgramRkam;
use App\Models\ProgramSemester;
use App\Models\ProgramTahunan;
use App\Models\RapatMadrasah;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\SupervisiPembelajaran;
use App\Models\TahunAjaran;
use App\Models\TransaksiKeuangan;
use App\Models\Ujian;
use Illuminate\Support\Facades\DB;

class SinkronisasiEdmService
{
    public function siapkanEvaluasi(TahunAjaran $tahunAjaran, ?int $userId = null): EdmEvaluasi
    {
        return DB::transaction(function () use ($tahunAjaran, $userId): EdmEvaluasi {
            $evaluasi = EdmEvaluasi::query()->firstOrCreate(
                ['tahun_ajaran_id' => $tahunAjaran->id],
                [
                    'judul' => "Evaluasi Diri Madrasah (EDM) {$tahunAjaran->nama}",
                    'status' => 'draf',
                    'pembuat_id' => $userId,
                ],
            );

            $existing = $evaluasi->indikators()->pluck('kode')->all();

            foreach (KatalogEdm2026::semua() as $item) {
                if (! in_array($item['kode'], $existing, true)) {
                    $evaluasi->indikators()->create([
                        'aspek' => $item['aspek'],
                        'kode' => $item['kode'],
                        'pertanyaan' => $item['pertanyaan'],
                    ]);
                }
            }

            $this->sinkronkanSemua($evaluasi);

            return $evaluasi->fresh(['indikators.buktis', 'tahunAjaran']) ?? $evaluasi;
        });
    }

    public function sinkronkanSemua(EdmEvaluasi $evaluasi): void
    {
        $tahunAjaran = $evaluasi->tahunAjaran;
        $jumlahGuru = Gtk::query()->where('is_aktif', true)->where('jenis_gtk', JenisGtk::Guru)->count();
        $jumlahTendik = Gtk::query()->where('is_aktif', true)->where('jenis_gtk', '!=', JenisGtk::Guru)->count();
        $jumlahGtk = Gtk::query()->where('is_aktif', true)->count();
        $jumlahSiswa = Siswa::query()->where('status', StatusSiswa::Aktif)->count();

        $evaluasi->loadMissing('indikators');

        foreach ($evaluasi->indikators as $indikator) {
            $hasil = $this->hitungIndikator($indikator->kode, $tahunAjaran, $jumlahGuru, $jumlahTendik, $jumlahGtk, $jumlahSiswa);

            $galeriCount = GaleriKegiatan::query()
                ->whereJsonContains('kode_butir_edm', $indikator->kode)
                ->count();

            $metrik = $hasil['metrik'];
            $metrik['jumlah_galeri_tertaut'] = $galeriCount;

            $indikator->forceFill([
                'skor_saran' => $hasil['skor_saran'],
                'metrik_sistem' => $metrik,
                'rekap_tabel' => $hasil['rekap_tabel'],
                'terakhir_sinkron_at' => now(),
            ])->save();
        }
    }

    /**
     * @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null}
     */
    protected function hitungIndikator(
        string $kode,
        TahunAjaran $tahunAjaran,
        int $jumlahGuru,
        int $jumlahTendik,
        int $jumlahGtk,
        int $jumlahSiswa,
    ): array {
        return match ($kode) {
            'A1' => $this->hitungA1($tahunAjaran),
            'A2' => $this->hitungA2($jumlahGuru),
            'A3' => $this->hitungA3($tahunAjaran, $jumlahGuru),
            'A4' => $this->hitungA4($tahunAjaran, $jumlahSiswa),
            'A5' => $this->hitungA5($jumlahSiswa),
            'A6' => $this->hitungA6($tahunAjaran, $jumlahGtk),
            'A7' => $this->hitungA7($tahunAjaran),
            'B1' => $this->hitungB1($tahunAjaran),
            'B2' => $this->hitungB2($tahunAjaran, $jumlahGuru),
            'B3' => $this->hitungB3($tahunAjaran, $jumlahGuru),
            'B4' => $this->hitungB4($tahunAjaran, $jumlahTendik),
            'C1' => $this->hitungC1($tahunAjaran, $jumlahGuru),
            'C2' => $this->hitungC2($jumlahGuru),
            'C3' => $this->hitungC3($tahunAjaran, $jumlahGuru),
            'C4' => $this->hitungC4($tahunAjaran, $jumlahGuru),
            'C5' => $this->hitungC5($tahunAjaran, $jumlahGuru),
            'C6' => $this->hitungC6($tahunAjaran, $jumlahGuru),
            'C7' => $this->hitungC7($tahunAjaran),
            'D1' => $this->hitungD1(),
            'D2' => $this->hitungD2(),
            'D3' => $this->hitungD3(),
            'D4' => $this->hitungD4($tahunAjaran, $jumlahGuru),
            'D5' => $this->hitungD5(),
            'E1' => $this->hitungE1($tahunAjaran),
            'E2' => $this->hitungE2($tahunAjaran),
            'E3' => $this->hitungE3(),
            default => [
                'skor_saran' => null,
                'metrik' => ['ringkasan' => 'Data indikator belum tersedia.', 'sumber' => 'Sistem'],
                'rekap_tabel' => null,
            ],
        };
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA1(TahunAjaran $tahunAjaran): array
    {
        $catatan = KegiatanIbadahSiswa::query()->with('kelas')->whereBelongsTo($tahunAjaran)->latest('tanggal')->get();
        $pelanggaran = PelanggaranSiswa::query()->whereBelongsTo($tahunAjaran)->count();

        $totalSiswa = (int) $catatan->sum('jumlah_siswa');
        $totalIkut = (int) $catatan->sum('jumlah_ikut');
        $adaAlasan = $catatan->contains(fn (KegiatanIbadahSiswa $c): bool => filled($c->alasan_ketidakikutsertaan));
        $persen = $totalSiswa > 0 ? round(($totalIkut / $totalSiswa) * 100, 1) : null;

        $skor = match (true) {
            $persen === null => null,
            $persen < 70 => 1,
            $persen < 90 => 2,
            ! $adaAlasan => 3,
            default => 4,
        };

        $baris = $catatan->take(25)->map(fn (KegiatanIbadahSiswa $c): array => [
            $c->tanggal->format('d/m/Y'),
            $c->kelas->nama ?? '-',
            KegiatanIbadahSiswa::JENIS[$c->jenis] ?? $c->jenis,
            "{$c->jumlah_ikut} / {$c->jumlah_siswa}",
            $c->alasan_ketidakikutsertaan ?? '-',
        ])->values()->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => $persen !== null
                    ? "Rata-rata keikutsertaan ibadah rutin {$persen}% ({$catatan->count()} catatan kegiatan). Pelanggaran tata tertib: {$pelanggaran} kasus."
                    : "Belum ada catatan pembiasaan ibadah di Panel Guru. Pelanggaran tata tertib: {$pelanggaran} kasus.",
                'persentase_ikut' => $persen,
                'pelanggaran_tahun_ini' => $pelanggaran,
                'sumber' => 'KegiatanIbadahSiswa & PelanggaranSiswa',
            ],
            'rekap_tabel' => $baris !== [] ? [
                'kolom' => ['Tanggal', 'Kelas', 'Kegiatan', 'Keikutsertaan', 'Alasan Tidak Ikut'],
                'baris' => $baris,
            ] : null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA2(int $jumlahGuru): array
    {
        $totalPresensi = PresensiGtk::query()->count();
        $hadir = PresensiGtk::query()->where('status', 'hadir')->count();
        $jurnalTotal = JurnalMengajar::query()->count();
        $jurnalTerlaksana = JurnalMengajar::query()->whereIn('status', ['terlaksana', 'digantikan'])->count();

        $persen = $totalPresensi > 0 ? round(($hadir / $totalPresensi) * 100, 1) : null;
        $skor = match (true) {
            $persen === null => null,
            $persen < 80 => 1,
            $persen < 95 => 2,
            $jurnalTerlaksana < $jurnalTotal => 3,
            default => 4,
        };

        $baris = Gtk::query()
            ->where('is_aktif', true)
            ->where('jenis_gtk', JenisGtk::Guru)
            ->orderBy('nama')
            ->limit(30)
            ->get()
            ->map(function (Gtk $gtk): array {
                $total = PresensiGtk::query()->where('gtk_id', $gtk->id)->count();
                $jmlHadir = PresensiGtk::query()->where('gtk_id', $gtk->id)->where('status', 'hadir')->count();
                $jurnal = JurnalMengajar::query()->where('guru_id', $gtk->id)->count();

                return [$gtk->nama, $gtk->nip ?? '-', "{$jmlHadir} / {$total}", (string) $jurnal];
            })
            ->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => $persen !== null
                    ? "Kehadiran GTK tercatat {$persen}% ({$hadir} dari {$totalPresensi} presensi), jurnal mengajar terlaksana/digantikan {$jurnalTerlaksana} dari {$jurnalTotal} sesi."
                    : "Belum ada data presensi GTK ({$jumlahGuru} guru aktif).",
                'persentase_hadir' => $persen,
                'sumber' => 'PresensiGtk & JurnalMengajar',
            ],
            'rekap_tabel' => [
                'kolom' => ['Nama Guru', 'NIP', 'Presensi Hadir', 'Jurnal Mengajar'],
                'baris' => $baris,
            ],
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA3(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $supervisi = SupervisiPembelajaran::query()->with('gtk')->whereBelongsTo($tahunAjaran)->latest('tanggal')->get();
        $guruDisupervisi = $supervisi->pluck('gtk_id')->unique()->count();
        $persen = $jumlahGuru > 0 ? round(($guruDisupervisi / $jumlahGuru) * 100, 1) : null;

        $skor = match (true) {
            $persen === null || $supervisi->isEmpty() => 1,
            $persen < 80 => 1,
            $persen < 90 => 2,
            $persen < 100 => 3,
            default => 4,
        };

        $baris = $supervisi->take(30)->map(fn (SupervisiPembelajaran $s): array => [
            $s->tanggal->format('d/m/Y'),
            $s->gtk->nama ?? '-',
            $s->nomor_sk ?? '-',
            $s->tindak_lanjut ?? '-',
        ])->values()->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruDisupervisi} dari {$jumlahGuru} guru (".($persen ?? 0).'%) telah disupervisi dan ditindaklanjuti pada tahun ajaran ini.',
                'guru_disupervisi' => $guruDisupervisi,
                'sumber' => 'SupervisiPembelajaran',
            ],
            'rekap_tabel' => $baris !== [] ? [
                'kolom' => ['Tanggal', 'Guru', 'No. SK', 'Tindak Lanjut'],
                'baris' => $baris,
            ] : null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA4(TahunAjaran $tahunAjaran, int $jumlahSiswa): array
    {
        $prestasiTotal = PrestasiSiswa::query()->whereBelongsTo($tahunAjaran)->count();
        $prestasiNasional = PrestasiSiswa::query()
            ->whereBelongsTo($tahunAjaran)
            ->whereIn('tingkat', ['nasional', 'internasional'])
            ->count();
        $ekskulAktif = Ekstrakurikuler::query()->count();

        $skor = match (true) {
            $prestasiNasional >= 3 => 4,
            $prestasiTotal >= 3 && $ekskulAktif >= 1 => 3,
            $prestasiTotal >= 1 || $ekskulAktif >= 1 => 2,
            default => $jumlahSiswa > 0 ? 1 : null,
        };

        $baris = PrestasiSiswa::query()
            ->with('siswa')
            ->whereBelongsTo($tahunAjaran)
            ->latest('tanggal')
            ->limit(25)
            ->get()
            ->map(fn (PrestasiSiswa $p): array => [
                $p->siswa->nama_lengkap ?? '-',
                $p->nama_prestasi,
                ucfirst((string) $p->tingkat),
                $p->peringkat ?? '-',
            ])
            ->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "Tercatat {$prestasiTotal} prestasi siswa ({$prestasiNasional} tingkat nasional/internasional) dan {$ekskulAktif} kegiatan ekstrakurikuler.",
                'sumber' => 'PrestasiSiswa & Ekstrakurikuler',
            ],
            'rekap_tabel' => [
                'kolom' => ['Nama Siswa', 'Prestasi', 'Tingkat', 'Peringkat'],
                'baris' => $baris,
            ],
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA5(int $jumlahSiswa): array
    {
        $jumlahBuku = BukuPerpustakaan::query()->where('is_aktif', true)->count();
        $totalDibaca = (int) BukuPerpustakaan::query()->where('is_aktif', true)->sum('jumlah_dibaca');

        $baris = BukuPerpustakaan::query()
            ->where('is_aktif', true)
            ->orderByDesc('jumlah_dibaca')
            ->limit(20)
            ->get()
            ->map(fn (BukuPerpustakaan $b): array => [
                $b->judul,
                BukuPerpustakaan::KATEGORI[$b->kategori] ?? $b->kategori,
                $b->tingkat !== null ? "Kelas {$b->tingkat}" : 'Umum',
                number_format($b->jumlah_dibaca).'x',
            ])
            ->all();

        return [
            'skor_saran' => $jumlahBuku > 0 && $totalDibaca > 0 ? 3 : ($jumlahBuku > 0 ? 2 : 1),
            'metrik' => [
                'ringkasan' => "Perpustakaan digital memiliki {$jumlahBuku} judul aktif dengan total {$totalDibaca} kali akses baca untuk {$jumlahSiswa} siswa aktif.",
                'sumber' => 'BukuPerpustakaan',
            ],
            'rekap_tabel' => [
                'kolom' => ['Judul Buku', 'Kategori', 'Tingkat', 'Dibaca'],
                'baris' => $baris,
            ],
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA6(TahunAjaran $tahunAjaran, int $jumlahGtk): array
    {
        $gtkDenganPembagian = PembagianMengajar::query()->whereBelongsTo($tahunAjaran)->distinct('gtk_id')->count('gtk_id');
        $gtkPelatihan = PelatihanGtk::query()->whereBelongsTo($tahunAjaran)->distinct('gtk_id')->count('gtk_id');
        $gtkSupervisi = SupervisiPembelajaran::query()->whereBelongsTo($tahunAjaran)->distinct('gtk_id')->count('gtk_id');
        $aspekTerpenuhi = ($gtkDenganPembagian > 0 ? 2 : 0) + ($gtkPelatihan > 0 ? 2 : 0) + ($gtkSupervisi > 0 ? 1 : 0);

        $skor = match ($aspekTerpenuhi) {
            5 => 4,
            4 => 3,
            3 => 2,
            default => 1,
        };

        $baris = Gtk::query()
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->limit(30)
            ->get()
            ->map(function (Gtk $gtk) use ($tahunAjaran): array {
                $jp = (int) PembagianMengajar::query()->whereBelongsTo($tahunAjaran)->where('gtk_id', $gtk->id)->sum('jp_per_minggu');
                $diklat = PelatihanGtk::query()->whereBelongsTo($tahunAjaran)->where('gtk_id', $gtk->id)->count();

                return [$gtk->nama, $gtk->jenis_gtk->getLabel(), "{$jp} JP", "{$diklat} kegiatan"];
            })
            ->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$gtkDenganPembagian} dari {$jumlahGtk} GTK memiliki data penugasan/beban JP dan {$gtkPelatihan} GTK aktif mengikuti forum/pelatihan.",
                'sumber' => 'Gtk, PembagianMengajar & PelatihanGtk',
            ],
            'rekap_tabel' => [
                'kolom' => ['Nama GTK', 'Jenis', 'Beban Kerja', 'Pelatihan/Forum'],
                'baris' => $baris,
            ],
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungA7(TahunAjaran $tahunAjaran): array
    {
        $rapats = RapatMadrasah::query()->whereBelongsTo($tahunAjaran)->latest('tanggal')->get();
        $jumlah = $rapats->count();
        $skor = match (true) {
            $jumlah >= 3 => 4,
            $jumlah === 2 => 3,
            $jumlah === 1 => 2,
            default => 1,
        };

        $baris = $rapats->take(20)->map(fn (RapatMadrasah $r): array => [
            $r->tanggal->format('d/m/Y'),
            $r->judul,
            RapatMadrasah::JENIS[$r->jenis] ?? $r->jenis,
            $r->melibatkan_komite ? 'Ya' : 'Tidak',
        ])->values()->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "Tercatat {$jumlah} pertemuan/rapat madrasah pada tahun ajaran ini.",
                'sumber' => 'RapatMadrasah',
            ],
            'rekap_tabel' => $baris !== [] ? [
                'kolom' => ['Tanggal', 'Judul Rapat', 'Jenis', 'Komite/Ortu'],
                'baris' => $baris,
            ] : null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungB1(TahunAjaran $tahunAjaran): array
    {
        $jumlah = PelatihanGtk::query()
            ->whereBelongsTo($tahunAjaran)
            ->where('jenis', 'pelatihan_kepala')
            ->count();

        $skor = match (true) {
            $jumlah >= 3 => 4,
            $jumlah === 2 => 3,
            $jumlah === 1 => 2,
            default => 1,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "Kepala Madrasah tercatat mengikuti {$jumlah} kegiatan pengembangan diri (KKM/workshop/pelatihan) tahun ini.",
                'sumber' => 'PelatihanGtk',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungB2(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruIkut = PelatihanGtk::query()
            ->whereBelongsTo($tahunAjaran)
            ->whereHas('gtk', fn ($q) => $q->where('jenis_gtk', JenisGtk::Guru))
            ->distinct('gtk_id')
            ->count('gtk_id');

        $persen = $jumlahGuru > 0 ? round(($guruIkut / $jumlahGuru) * 100, 1) : null;
        $skor = match (true) {
            $persen === null || $guruIkut === 0 => 1,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruIkut} dari {$jumlahGuru} guru (".($persen ?? 0).'%) aktif mengikuti KKG/MGMP/workshop/pelatihan.',
                'sumber' => 'PelatihanGtk',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungB3(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruSasaran = (int) ProgramRkam::query()->whereBelongsTo($tahunAjaran)->max('jumlah_guru_sasaran');
        $persen = $jumlahGuru > 0 ? round(($guruSasaran / $jumlahGuru) * 100, 1) : null;

        $skor = match (true) {
            $persen === null || $guruSasaran === 0 => 1,
            $persen < 50 => 1,
            $persen < 65 => 2,
            $persen < 80 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruSasaran} dari {$jumlahGuru} guru (".($persen ?? 0).'%) direncanakan mengikuti program peningkatan mutu pada RKAM.',
                'sumber' => 'ProgramRkam',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungB4(TahunAjaran $tahunAjaran, int $jumlahTendik): array
    {
        $tendikIkut = PelatihanGtk::query()
            ->whereBelongsTo($tahunAjaran)
            ->whereHas('gtk', fn ($q) => $q->where('jenis_gtk', '!=', JenisGtk::Guru))
            ->distinct('gtk_id')
            ->count('gtk_id');

        $persen = $jumlahTendik > 0 ? round(($tendikIkut / $jumlahTendik) * 100, 1) : null;
        $skor = match (true) {
            $persen === null || $tendikIkut === 0 => 1,
            $persen < 50 => 1,
            $persen < 65 => 2,
            $persen < 80 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$tendikIkut} dari {$jumlahTendik} tenaga kependidikan (".($persen ?? 0).'%) mengikuti pelatihan/workshop/bimtek.',
                'sumber' => 'PelatihanGtk',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC1(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruSudahMenyusun = AlurTujuanPembelajaran::query()->whereBelongsTo($tahunAjaran)->pluck('gtk_id')
            ->merge(ProgramTahunan::query()->whereBelongsTo($tahunAjaran)->pluck('gtk_id'))
            ->merge(ProgramSemester::query()->whereBelongsTo($tahunAjaran)->pluck('gtk_id'))
            ->merge(PerangkatAjar::query()->whereBelongsTo($tahunAjaran)->pluck('gtk_id'))
            ->filter()
            ->unique()
            ->count();

        $persen = $jumlahGuru > 0 ? round(($guruSudahMenyusun / $jumlahGuru) * 100, 1) : null;
        $skor = match (true) {
            $persen === null => null,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        $baris = Gtk::query()
            ->where('is_aktif', true)
            ->where('jenis_gtk', JenisGtk::Guru)
            ->orderBy('nama')
            ->limit(30)
            ->get()
            ->map(function (Gtk $gtk) use ($tahunAjaran): array {
                $prota = ProgramTahunan::query()->whereBelongsTo($tahunAjaran)->where('gtk_id', $gtk->id)->count();
                $prosem = ProgramSemester::query()->whereBelongsTo($tahunAjaran)->where('gtk_id', $gtk->id)->count();
                $atp = AlurTujuanPembelajaran::query()->whereBelongsTo($tahunAjaran)->where('gtk_id', $gtk->id)->count();
                $modul = PerangkatAjar::query()->whereBelongsTo($tahunAjaran)->where('gtk_id', $gtk->id)->count();

                return [$gtk->nama, (string) $prota, (string) $prosem, (string) ($atp + $modul)];
            })
            ->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => $persen !== null
                    ? "{$guruSudahMenyusun} dari {$jumlahGuru} guru ({$persen}%) telah menyusun perangkat pembelajaran (Prota/Prosem/ATP/Modul Ajar)."
                    : 'Belum ada guru aktif terdaftar.',
                'sumber' => 'Prota, Prosem, ATP & PerangkatAjar',
            ],
            'rekap_tabel' => [
                'kolom' => ['Nama Guru', 'Prota', 'Prosem', 'ATP / Modul'],
                'baris' => $baris,
            ],
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC2(int $jumlahGuru): array
    {
        $guruMetode = JurnalMengajar::query()
            ->whereNotNull('metode_pembelajaran')
            ->distinct('guru_id')
            ->count('guru_id');

        $persen = $jumlahGuru > 0 ? round(($guruMetode / $jumlahGuru) * 100, 1) : null;
        $skor = match (true) {
            $persen === null || $guruMetode === 0 => 1,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen <= 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruMetode} dari {$jumlahGuru} guru (".($persen ?? 0).'%) telah mencatat ragam metode pembelajaran di Jurnal Mengajar.',
                'sumber' => 'JurnalMengajar',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC3(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruJurnalMedia = JurnalMengajar::query()->whereNotNull('media_pembelajaran')->pluck('guru_id');
        $guruPerangkat = PerangkatAjar::query()->whereBelongsTo($tahunAjaran)->pluck('gtk_id');
        $guruMedia = $guruJurnalMedia->merge($guruPerangkat)->filter()->unique()->count();
        $ujianCbt = Ujian::query()->count();
        $persen = $jumlahGuru > 0 ? round(($guruMedia / $jumlahGuru) * 100, 1) : null;

        $skor = match (true) {
            $persen === null => null,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruMedia} dari {$jumlahGuru} guru (".($persen ?? 0)."%) menggunakan media ajar/TIK dan tercatat {$ujianCbt} paket ujian CBT.",
                'sumber' => 'JurnalMengajar, PerangkatAjar & Ujian CBT',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC4(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruOtentik = SupervisiPembelajaran::query()
            ->whereBelongsTo($tahunAjaran)
            ->where('penilaian_otentik', true)
            ->distinct('gtk_id')
            ->count('gtk_id');

        $persen = $jumlahGuru > 0 ? round(($guruOtentik / $jumlahGuru) * 100, 1) : null;
        $skor = match (true) {
            $persen === null || $guruOtentik === 0 => 1,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruOtentik} dari {$jumlahGuru} guru (".($persen ?? 0).'%) terverifikasi melakukan penilaian otentik dalam hasil supervisi.',
                'sumber' => 'SupervisiPembelajaran',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC5(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $raporCount = Rapor::query()->whereBelongsTo($tahunAjaran)->count();
        $nilaiCount = NilaiRapor::query()->whereHas('rapor', fn ($q) => $q->whereBelongsTo($tahunAjaran))->count();

        return [
            'skor_saran' => $raporCount > 0 && $nilaiCount > 0 ? 3 : ($nilaiCount > 0 ? 2 : 1),
            'metrik' => [
                'ringkasan' => "Tercatat {$raporCount} rapor dan {$nilaiCount} entri nilai rapor pada tahun ajaran ini ({$jumlahGuru} guru aktif).",
                'sumber' => 'Rapor & NilaiRapor',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC6(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruProgram = KegiatanRemedial::query()->whereBelongsTo($tahunAjaran)->distinct('gtk_id')->count('gtk_id');
        $persen = $jumlahGuru > 0 ? round(($guruProgram / $jumlahGuru) * 100, 1) : null;

        $skor = match (true) {
            $persen === null || $guruProgram === 0 => 1,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruProgram} dari {$jumlahGuru} guru (".($persen ?? 0).'%) telah menyusun/melaksanakan program remedial, pengayaan, atau konseling.',
                'sumber' => 'KegiatanRemedial',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungC7(TahunAjaran $tahunAjaran): array
    {
        $kegiatan = KegiatanRemedial::query()->with(['kelas', 'mataPelajaran'])->whereBelongsTo($tahunAjaran)->latest('tanggal')->get();
        $sasaran = (int) $kegiatan->sum('jumlah_sasaran');
        $hadir = (int) $kegiatan->sum('jumlah_hadir');
        $persen = $sasaran > 0 ? round(($hadir / $sasaran) * 100, 1) : null;

        $skor = match (true) {
            $kegiatan->isEmpty() => 1,
            $persen === null || $persen < 80 => 2,
            $persen < 95 => 3,
            default => 4,
        };

        $baris = $kegiatan->take(25)->map(fn (KegiatanRemedial $k): array => [
            $k->tanggal->format('d/m/Y'),
            KegiatanRemedial::JENIS[$k->jenis] ?? $k->jenis,
            $k->kelas->nama ?? '-',
            "{$k->jumlah_hadir} / {$k->jumlah_sasaran}",
        ])->values()->all();

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => $kegiatan->isNotEmpty()
                    ? "Tercatat {$kegiatan->count()} sesi remedial/pengayaan dengan kehadiran siswa {$persen}% ({$hadir}/{$sasaran})."
                    : 'Belum ada kegiatan remedial/pengayaan tercatat di Panel Guru.',
                'sumber' => 'KegiatanRemedial',
            ],
            'rekap_tabel' => $baris !== [] ? [
                'kolom' => ['Tanggal', 'Program', 'Kelas', 'Kehadiran'],
                'baris' => $baris,
            ] : null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungD1(): array
    {
        $mapelTotal = MataPelajaran::query()->count();
        $bukuPaket = BukuPerpustakaan::query()->where('is_aktif', true)->where('kategori', 'paket')->count();
        $bukuTotal = BukuPerpustakaan::query()->where('is_aktif', true)->count();
        $bukuCetak = (int) InventarisMedia::query()->where('kategori', 'buku_cetak')->sum('jumlah');

        $skor = match (true) {
            $bukuTotal === 0 && $bukuCetak === 0 => 1,
            $mapelTotal > 0 && ($bukuPaket + $bukuCetak) >= $mapelTotal => 4,
            $bukuPaket > 0 || $bukuCetak > 0 => 3,
            default => 2,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "Tersedia {$bukuTotal} e-book ({$bukuPaket} buku paket) serta {$bukuCetak} eksemplar buku cetak untuk {$mapelTotal} mata pelajaran.",
                'sumber' => 'BukuPerpustakaan & InventarisMedia',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungD2(): array
    {
        $mapelTotal = MataPelajaran::query()->count();
        $mediaNonDigital = InventarisMedia::query()->where('kategori', 'non_digital')->where('kondisi_baik', true)->count();
        $persen = $mapelTotal > 0 ? min(100.0, round(($mediaNonDigital / $mapelTotal) * 100, 1)) : ($mediaNonDigital > 0 ? 100.0 : 0.0);

        $skor = match (true) {
            $mediaNonDigital === 0 || $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "Tercatat {$mediaNonDigital} jenis media/alat peraga non-digital kondisi baik di Inventaris Media.",
                'sumber' => 'InventarisMedia',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungD3(): array
    {
        $bukuDigital = BukuPerpustakaan::query()->where('is_aktif', true)->count();
        $ujianCbt = Ujian::query()->count();
        $mediaDigital = InventarisMedia::query()->where('kategori', 'digital')->where('kondisi_baik', true)->count();

        return [
            'skor_saran' => ($bukuDigital > 0 && ($ujianCbt > 0 || $mediaDigital > 0)) ? 4 : ($bukuDigital > 0 || $mediaDigital > 0 ? 3 : 1),
            'metrik' => [
                'ringkasan' => "Media digital: Perpustakaan Digital ({$bukuDigital} e-book), CBT ({$ujianCbt} paket ujian), dan {$mediaDigital} inventaris media TIK.",
                'sumber' => 'BukuPerpustakaan, Ujian CBT & InventarisMedia',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungD4(TahunAjaran $tahunAjaran, int $jumlahGuru): array
    {
        $guruJurnalBuku = JurnalMengajar::query()->where('pakai_buku_teks', true)->pluck('guru_id');
        $guruPerangkat = PerangkatAjar::query()->whereBelongsTo($tahunAjaran)->pluck('gtk_id');
        $guruPakai = $guruJurnalBuku->merge($guruPerangkat)->filter()->unique()->count();
        $persen = $jumlahGuru > 0 ? round(($guruPakai / $jumlahGuru) * 100, 1) : null;

        $skor = match (true) {
            $persen === null => null,
            $persen < 90 => 1,
            $persen < 95 => 2,
            $persen < 100 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => "{$guruPakai} dari {$jumlahGuru} guru (".($persen ?? 0).'%) tercatat menggunakan buku teks cetak/digital dalam pembelajaran.',
                'sumber' => 'JurnalMengajar & PerangkatAjar',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungD5(): array
    {
        $itemDipakaiSiswa = InventarisMedia::query()->where('digunakan_siswa', true)->count();
        $eBookAktif = BukuPerpustakaan::query()->where('is_aktif', true)->count();

        return [
            'skor_saran' => $itemDipakaiSiswa > 0 && $eBookAktif > 0 ? 4 : ($itemDipakaiSiswa > 0 || $eBookAktif > 0 ? 3 : 1),
            'metrik' => [
                'ringkasan' => "{$itemDipakaiSiswa} item inventaris buku/media tercatat digunakan siswa bersama {$eBookAktif} koleksi e-book perpustakaan.",
                'sumber' => 'InventarisMedia & BukuPerpustakaan',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungE1(TahunAjaran $tahunAjaran): array
    {
        $rkam = ProgramRkam::query()->whereBelongsTo($tahunAjaran)->where('pakai_edm_rkam', true)->where('disahkan_kepala', true)->exists();
        $rapat = RapatMadrasah::query()->whereBelongsTo($tahunAjaran)->where('jenis', 'edm_rkam')->get();

        $libatkanGuruTendik = $rapat->contains(fn (RapatMadrasah $r): bool => $r->melibatkan_guru && $r->melibatkan_tendik);
        $libatkanKomite = $rapat->contains(fn (RapatMadrasah $r): bool => $r->melibatkan_komite);

        $skor = match (true) {
            ! $rkam => 1,
            $libatkanGuruTendik && $libatkanKomite => 4,
            $libatkanGuruTendik => 3,
            default => 2,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => $rkam
                    ? 'Program RKAM disusun dengan EDM/e-RKAM & disahkan Kepala Madrasah ('.($libatkanKomite ? 'melibatkan guru, tendik, dan komite' : 'belum melibatkan komite').').'
                    : 'Belum ada catatan penyusunan program di modul Program Mutu & e-RKAM.',
                'sumber' => 'ProgramRkam & RapatMadrasah',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungE2(TahunAjaran $tahunAjaran): array
    {
        $total = PelatihanGtk::query()->whereBelongsTo($tahunAjaran)->count();
        $dibantu = PelatihanGtk::query()->whereBelongsTo($tahunAjaran)->where('dapat_bantuan_biaya', true)->count();
        $persen = $total > 0 ? round(($dibantu / $total) * 100, 1) : null;

        $skor = match (true) {
            $persen === null || $dibantu === 0 => 1,
            $persen < 70 => 1,
            $persen < 80 => 2,
            $persen < 90 => 3,
            default => 4,
        };

        return [
            'skor_saran' => $skor,
            'metrik' => [
                'ringkasan' => $total > 0
                    ? "{$dibantu} dari {$total} keikutsertaan pelatihan GTK ({$persen}%) mendapat bantuan biaya madrasah."
                    : 'Belum ada data pelatihan GTK tahun ini.',
                'sumber' => 'PelatihanGtk',
            ],
            'rekap_tabel' => null,
        ];
    }

    /** @return array{skor_saran: int|null, metrik: array<string, mixed>, rekap_tabel: array{kolom: list<string>, baris: array<int, list<string>>}|null} */
    protected function hitungE3(): array
    {
        $jumlahTransaksi = TransaksiKeuangan::query()->count();

        return [
            'skor_saran' => $jumlahTransaksi > 0 ? 2 : 1,
            'metrik' => [
                'ringkasan' => "Tercatat {$jumlahTransaksi} transaksi keuangan madrasah. Unggah bukti penyampaian laporan ke komite & orang tua untuk Tingkat 3–4.",
                'sumber' => 'TransaksiKeuangan',
            ],
            'rekap_tabel' => null,
        ];
    }
}
