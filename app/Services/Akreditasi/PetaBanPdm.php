<?php

namespace App\Services\Akreditasi;

use App\Enums\StatusSiswa;
use App\Models\AkreditasiBukti;
use App\Models\AkreditasiDokumen;
use App\Models\Ekstrakurikuler;
use App\Models\GaleriKegiatan;
use App\Models\Gtk;
use App\Models\JurnalMengajar;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PelanggaranSiswa;
use App\Models\PembagianMengajar;
use App\Models\PerangkatAjar;
use App\Models\PrestasiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\TahunAjaran;
use App\Models\TransaksiKeuangan;
use App\Models\Ujian;
use Illuminate\Support\Facades\DB;

class PetaBanPdm
{
    /**
     * 6 Dokumen Wajib (DW) Pra-Visitasi Sispena BAN-PDM MTs.
     *
     * @return list<array{kode: string, judul: string, deskripsi: string}>
     */
    public static function dokumenWajib(): array
    {
        return [
            [
                'kode' => 'dw_1',
                'judul' => 'Kurikulum Satuan Pendidikan (KSP / KOM MTs)',
                'deskripsi' => 'Analisis karakteristik madrasah, visi-misi-tujuan, pengorganisasian pembelajaran (intrakurikuler, P5-PPRA, ekstrakurikuler), perencanaan pembelajaran, serta SK Tim & Berita Acara.',
            ],
            [
                'kode' => 'dw_2',
                'judul' => 'Rencana Kerja Tahunan (RKT) Madrasah',
                'deskripsi' => 'Matriks program kerja 1 tahun ajaran berbasis evaluasi diri/Rapor Pendidikan: masalah mutu, akar masalah, program pembenahan, penanggung jawab, dan target.',
            ],
            [
                'kode' => 'dw_3',
                'judul' => 'Rencana Kegiatan dan Anggaran Madrasah (RKAS / RKAM)',
                'deskripsi' => 'Rincian anggaran pendapatan dan belanja (BOS/Komite) yang selaras dengan program prioritas RKT.',
            ],
            [
                'kode' => 'dw_4',
                'judul' => 'Kalender Pendidikan Madrasah',
                'deskripsi' => 'Kalender bulanan, minggu efektif belajar, jadwal asesmen sumatif, jadwal blok P5-PPRA, hari libur, dan agenda pembiasaan.',
            ],
            [
                'kode' => 'dw_5',
                'judul' => 'Contoh Perencanaan Pembelajaran (Modul Ajar & P5-PPRA)',
                'deskripsi' => 'Sampel Modul Ajar lintas mapel (termasuk PAI/Bahasa Arab) memuat asesmen awal-formatif-sumatif serta 1 modul utuh Projek P5-PPRA.',
            ],
            [
                'kode' => 'dw_6',
                'judul' => 'Dokumentasi Lingkungan Belajar Madrasah',
                'deskripsi' => 'Kolase foto berlabel ruang kelas, perpustakaan, laboratorium, UKS, toilet, musala, kantin sehat, dan aktivitas belajar.',
            ],
        ];
    }

    /**
     * 14 Butir Instrumen Akreditasi Satuan Pendidikan (IASP) BAN-PDM.
     *
     * @return list<array{kode: string, nomor: int, komponen: int, komponen_nama: string, judul: string, bukti_kunci: string}>
     */
    public static function butir14(): array
    {
        return [
            ['kode' => 'butir_01', 'nomor' => 1, 'komponen' => 1, 'komponen_nama' => 'Kinerja Pendidik dalam Pembelajaran', 'judul' => 'Dukungan Sosial Emosional & Umpan Balik', 'bukti_kunci' => 'Catatan wali kelas di rapor, pembinaan siswa, remedial/pengayaan'],
            ['kode' => 'butir_02', 'nomor' => 2, 'komponen' => 1, 'komponen_nama' => 'Kinerja Pendidik dalam Pembelajaran', 'judul' => 'Pengelolaan Kelas & Disiplin Positif', 'bukti_kunci' => 'Kesepakatan kelas, tata tertib tanpa kekerasan, catatan piket'],
            ['kode' => 'butir_03', 'nomor' => 3, 'komponen' => 1, 'komponen_nama' => 'Kinerja Pendidik dalam Pembelajaran', 'judul' => 'Pembelajaran Efektif & Asesmen (PPA)', 'bukti_kunci' => 'ATP, Prota, Prosem, Modul Ajar, kisi-kisi/bank soal, jurnal mengajar'],
            ['kode' => 'butir_04', 'nomor' => 4, 'komponen' => 1, 'komponen_nama' => 'Kinerja Pendidik dalam Pembelajaran', 'judul' => 'Keimanan, Akhlak, Kebangsaan & Bernalar Kritis', 'bukti_kunci' => 'Pembiasaan ibadah, modul & portofolio karya P5-PPRA, ekstrakurikuler'],

            ['kode' => 'butir_05', 'nomor' => 5, 'komponen' => 2, 'komponen_nama' => 'Kepemimpinan Kepala Madrasah', 'judul' => 'Budaya Refleksi & Evaluasi Kinerja Guru', 'bukti_kunci' => 'Jadwal & instrumen supervisi akademik, notulen MGMP internal, PKG'],
            ['kode' => 'butir_06', 'nomor' => 6, 'komponen' => 2, 'komponen_nama' => 'Kepemimpinan Kepala Madrasah', 'judul' => 'Layanan Partisipatif & Kemitraan', 'bukti_kunci' => 'Notulen rapat komite/orang tua, surat undangan, MoU Puskesmas/KUA'],
            ['kode' => 'butir_07', 'nomor' => 7, 'komponen' => 2, 'komponen_nama' => 'Kepemimpinan Kepala Madrasah', 'judul' => 'Pengelolaan Anggaran Transparan', 'bukti_kunci' => 'RKAS/RKAM, Buku Kas Umum, laporan realisasi keuangan'],
            ['kode' => 'butir_08', 'nomor' => 8, 'komponen' => 2, 'komponen_nama' => 'Kepemimpinan Kepala Madrasah', 'judul' => 'Pengelolaan Sarana dan Prasarana', 'bukti_kunci' => 'Kartu Inventaris Ruangan (KIR), daftar inventaris lab/perpustakaan'],
            ['kode' => 'butir_09', 'nomor' => 9, 'komponen' => 2, 'komponen_nama' => 'Kepemimpinan Kepala Madrasah', 'judul' => 'Pengembangan Kurikulum Satuan Pendidikan (KSP)', 'bukti_kunci' => 'Dokumen KSP MTs lengkap, SK Tim Pengembang Kurikulum, berita acara'],

            ['kode' => 'butir_10', 'nomor' => 10, 'komponen' => 3, 'komponen_nama' => 'Iklim Lingkungan Belajar', 'judul' => 'Iklim Kebinekaan & Kesetaraan', 'bukti_kunci' => 'Demografi siswa/GTK, kegiatan moderasi beragama & kebangsaan'],
            ['kode' => 'butir_11', 'nomor' => 11, 'komponen' => 3, 'komponen_nama' => 'Iklim Lingkungan Belajar', 'judul' => 'Lingkungan Belajar Inklusif', 'bukti_kunci' => 'SOP layanan inklusif/ramah anak, pemetaan kebutuhan belajar siswa'],
            ['kode' => 'butir_12', 'nomor' => 12, 'komponen' => 3, 'komponen_nama' => 'Iklim Lingkungan Belajar', 'judul' => 'Keamanan Psikis & Pencegahan Kekerasan', 'bukti_kunci' => 'SK TPPK, SOP anti-perundungan, log penanganan pelanggaran siswa'],
            ['kode' => 'butir_13', 'nomor' => 13, 'komponen' => 3, 'komponen_nama' => 'Iklim Lingkungan Belajar', 'judul' => 'Keselamatan Fisik & Mitigasi Bencana', 'bukti_kunci' => 'Ceklis kelayakan bangunan, daftar P3K, jalur evakuasi bencana'],
            ['kode' => 'butir_14', 'nomor' => 14, 'komponen' => 3, 'komponen_nama' => 'Iklim Lingkungan Belajar', 'judul' => 'Lingkungan Sehat, UKS & Kesehatan Mental', 'bukti_kunci' => 'Program UKS, pengawasan kantin sehat, edukasi kesehatan'],
        ];
    }

    /**
     * Ensure all 6 DW + 14 DKA documents exist for the given academic year.
     */
    public function inisialisasiDokumen(TahunAjaran $tahun, ?int $userId = null): int
    {
        $dibuat = 0;

        DB::transaction(function () use ($tahun, $userId, &$dibuat): void {
            foreach (static::dokumenWajib() as $dw) {
                $dok = AkreditasiDokumen::query()->firstOrCreate(
                    ['tahun_ajaran_id' => $tahun->id, 'kode' => $dw['kode']],
                    [
                        'kategori' => 'dokumen_wajib',
                        'komponen' => null,
                        'judul' => $dw['judul'],
                        'status' => 'belum',
                        'user_id' => $userId,
                    ],
                );
                if ($dok->wasRecentlyCreated) {
                    $dibuat++;
                }
            }

            foreach (static::butir14() as $butir) {
                $dok = AkreditasiDokumen::query()->firstOrCreate(
                    ['tahun_ajaran_id' => $tahun->id, 'kode' => $butir['kode']],
                    [
                        'kategori' => 'dka_butir',
                        'komponen' => $butir['komponen'],
                        'judul' => "Butir {$butir['nomor']}: {$butir['judul']}",
                        'status' => 'belum',
                        'user_id' => $userId,
                    ],
                );
                if ($dok->wasRecentlyCreated) {
                    $dibuat++;
                }
            }
        });

        return $dibuat;
    }

    /**
     * Strictly anonymous aggregate stats from Aksara database for data-grounded AI generation (no PII).
     *
     * @return array<string, mixed>
     */
    public function ringkasanStatistik(TahunAjaran $tahun): array
    {
        $siswaPerTingkat = Siswa::query()
            ->where('status', StatusSiswa::Aktif)
            ->join('kelas', 'kelas.id', '=', 'siswas.kelas_id')
            ->selectRaw('kelas.tingkat, count(*) as total')
            ->groupBy('kelas.tingkat')
            ->pluck('total', 'tingkat')
            ->all();

        $pemasukan = (int) TransaksiKeuangan::query()->whereNull('dibatalkan_at')->where('jenis', 'pemasukan')->sum('nominal');
        $pengeluaran = (int) TransaksiKeuangan::query()->whereNull('dibatalkan_at')->where('jenis', 'pengeluaran')->sum('nominal');

        return [
            'madrasah' => config('sekolah.nama', 'Madrasah Tsanawiyah'),
            'wilayah' => config('sekolah.wilayah', ''),
            'npsn' => config('sekolah.npsn', ''),
            'akreditasi_sebelumnya' => config('sekolah.akreditasi', ''),
            'tahun_ajaran' => $tahun->nama,
            'jumlah_siswa_aktif' => Siswa::query()->where('status', StatusSiswa::Aktif)->count(),
            'siswa_per_tingkat' => $siswaPerTingkat,
            'jumlah_rombel_aktif' => Kelas::query()->aktif()->count(),
            'jumlah_gtk_aktif' => Gtk::query()->aktif()->count(),
            'jumlah_mapel_aktif' => MataPelajaran::query()->where('is_aktif', true)->count(),
            'daftar_ekstrakurikuler' => Ekstrakurikuler::query()->aktif()->orderBy('nama')->pluck('nama')->all(),
            'jumlah_perangkat_ajar' => PerangkatAjar::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_jurnal_mengajar' => JurnalMengajar::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_bank_soal' => Soal::query()->count(),
            'jumlah_ujian' => Ujian::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_rapor' => Rapor::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_pembagian_mengajar' => PembagianMengajar::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_agenda_kalender' => KalenderAkademik::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'total_pemasukan_rp' => $pemasukan,
            'total_pengeluaran_rp' => $pengeluaran,
            'jumlah_prestasi_siswa' => PrestasiSiswa::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_catatan_pelanggaran' => PelanggaranSiswa::query()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jumlah_galeri_kegiatan' => GaleriKegiatan::query()->count(),
            'jumlah_surat_keluar' => SuratKeluar::query()->count(),
            'jumlah_surat_masuk' => SuratMasuk::query()->count(),
        ];
    }

    /**
     * Automatically tag existing Aksara records into AkreditasiBukti (Butir 01..14) without duplicates.
     */
    public function autoTagBukti(TahunAjaran $tahun, ?int $userId = null): int
    {
        $ditambahkan = 0;

        DB::transaction(function () use ($tahun, $userId, &$ditambahkan): void {
            // Galeri kegiatan -> Butir 04 (kegiatan karakter/P5) or Butir 10 (kebinekaan)
            foreach (GaleriKegiatan::query()->latest('id')->limit(30)->get() as $galeri) {
                $bukti = AkreditasiBukti::query()->firstOrCreate(
                    [
                        'tahun_ajaran_id' => $tahun->id,
                        'kode_butir' => 'butir_04',
                        'sumber_tipe' => 'galeri',
                        'sumber_id' => $galeri->id,
                    ],
                    [
                        'judul' => "Dokumentasi: {$galeri->judul}",
                        'keterangan' => $galeri->deskripsi,
                        'user_id' => $userId,
                    ],
                );
                if ($bukti->wasRecentlyCreated) {
                    $ditambahkan++;
                }
            }

            // Surat Keluar -> Butir 06 (kemitraan/rapat)
            foreach (SuratKeluar::query()->latest('id')->limit(30)->get() as $surat) {
                $bukti = AkreditasiBukti::query()->firstOrCreate(
                    [
                        'tahun_ajaran_id' => $tahun->id,
                        'kode_butir' => 'butir_06',
                        'sumber_tipe' => 'surat_keluar',
                        'sumber_id' => $surat->id,
                    ],
                    [
                        'judul' => "Surat Keluar: {$surat->perihal}",
                        'keterangan' => "Nomor: {$surat->nomor}",
                        'user_id' => $userId,
                    ],
                );
                if ($bukti->wasRecentlyCreated) {
                    $ditambahkan++;
                }
            }

            // Perangkat Ajar -> Butir 03 (pembelajaran & asesmen)
            foreach (PerangkatAjar::query()->where('tahun_ajaran_id', $tahun->id)->with('mataPelajaran')->limit(30)->get() as $pa) {
                $namaMapel = $pa->mataPelajaran instanceof MataPelajaran ? $pa->mataPelajaran->nama : '-';
                $namaKelas = (string) ($pa->getAttribute('kelas') ?? '-');
                $bukti = AkreditasiBukti::query()->firstOrCreate(
                    [
                        'tahun_ajaran_id' => $tahun->id,
                        'kode_butir' => 'butir_03',
                        'sumber_tipe' => 'perangkat_ajar',
                        'sumber_id' => $pa->id,
                    ],
                    [
                        'judul' => "Perangkat Ajar: {$namaMapel} ({$namaKelas})",
                        'keterangan' => 'ATP, PROTA, PROSEM & Modul Ajar',
                        'user_id' => $userId,
                    ],
                );
                if ($bukti->wasRecentlyCreated) {
                    $ditambahkan++;
                }
            }
        });

        return $ditambahkan;
    }

    /**
     * Compute overall readiness scores across 6 DW and 14 Butir.
     *
     * @return array{dw_selesai: int, dw_total: int, dka_selesai: int, dka_total: int, bukti_total: int, persen_keseluruhan: int}
     */
    public function analisisKesiapan(TahunAjaran $tahun): array
    {
        $dokumens = AkreditasiDokumen::query()->where('tahun_ajaran_id', $tahun->id)->get();
        $dwSelesai = $dokumens->where('kategori', 'dokumen_wajib')->whereIn('status', ['draf', 'final'])->count();
        $dkaSelesai = $dokumens->where('kategori', 'dka_butir')->whereIn('status', ['draf', 'final'])->count();
        $buktiTotal = AkreditasiBukti::query()->where('tahun_ajaran_id', $tahun->id)->count();

        $totalItem = 20; // 6 DW + 14 Butir
        $selesai = $dwSelesai + $dkaSelesai;

        return [
            'dw_selesai' => $dwSelesai,
            'dw_total' => 6,
            'dka_selesai' => $dkaSelesai,
            'dka_total' => 14,
            'bukti_total' => $buktiTotal,
            'persen_keseluruhan' => (int) round(($selesai / $totalItem) * 100),
        ];
    }
}
