<?php

namespace App\Services\Kurikulum;

use App\Models\AlurTujuanPembelajaran;
use App\Models\AlurTujuanPembelajaranItem;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Services\Gemini\KlienGemini;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * @phpstan-type AtpRow array{
 *     urutan: int,
 *     elemen_cp: string,
 *     capaian_pembelajaran: string,
 *     tujuan_pembelajaran: string,
 *     materi_pembelajaran: string,
 *     alur_tujuan_pembelajaran: string,
 *     alokasi_waktu_jp: int,
 *     keterangan: string|null
 * }
 */
class PenyusunAtpKurikulumMerdeka
{
    public function __construct(protected ?KlienGemini $klien = null)
    {
        $this->klien ??= app(KlienGemini::class);
    }

    public static function tebakJenjang(?int $tingkat): string
    {
        return match ($tingkat) {
            1, 2, 3, 4, 5, 6 => 'SD',
            7, 8, 9 => 'SMP',
            10, 11, 12 => 'SMA',
            default => 'SMP',
        };
    }

    public static function tebakFase(?int $tingkat): string
    {
        return match ($tingkat) {
            1, 2 => 'A',
            3, 4 => 'B',
            5, 6 => 'C',
            7, 8, 9 => 'D',
            10 => 'E',
            11, 12 => 'F',
            default => 'D',
        };
    }

    /**
     * @return list<AtpRow>
     */
    public function dapatkanDrafStandar(string $namaMapel, string $fase = 'D', string $jenjang = 'SMP'): array
    {
        $namaLower = mb_strtolower($namaMapel);

        if (str_contains($namaLower, 'matematik')) {
            return $this->drafMatematikaFaseD();
        }

        if (str_contains($namaLower, 'indonesia')) {
            return $this->drafBahasaIndonesiaFaseD();
        }

        if (str_contains($namaLower, 'inggris')) {
            return $this->drafBahasaInggrisFaseD();
        }

        if (str_contains($namaLower, 'ipa') || str_contains($namaLower, 'alam') || str_contains($namaLower, 'ipas')) {
            return $this->drafIpaFaseD();
        }

        if (str_contains($namaLower, 'ips') || str_contains($namaLower, 'sosial')) {
            return $this->drafIpsFaseD();
        }

        if (str_contains($namaLower, 'pancasila') || str_contains($namaLower, 'pkn')) {
            return $this->drafPancasilaFaseD();
        }

        if (str_contains($namaLower, 'informatik') || str_contains($namaLower, 'tik')) {
            return $this->drafInformatikaFaseD();
        }

        if (str_contains($namaLower, 'agama') || str_contains($namaLower, 'pai') || str_contains($namaLower, 'akidah') || str_contains($namaLower, 'qur') || str_contains($namaLower, 'fiqih') || str_contains($namaLower, 'ski')) {
            return $this->drafPaiFaseD($namaMapel);
        }

        if (str_contains($namaLower, 'pjok') || str_contains($namaLower, 'olahraga') || str_contains($namaLower, 'penjas')) {
            return $this->drafPjokFaseD();
        }

        return $this->drafGenerik($namaMapel, $fase);
    }

    /**
     * @return array{cp_fase: string, catatan: string, items: list<AtpRow>}
     */
    public function susunAtpLengkap(
        int $userId,
        string $namaMapel,
        string $namaKelas,
        string $fase,
        string $jenjang = 'SMP',
        ?string $instruksiKhusus = null
    ): array {
        $defaultCp = "Peserta didik memahami konsep esensial, keterampilan proses, serta penalaran kritis mata pelajaran {$namaMapel} Fase {$fase} jenjang {$jenjang}.";
        $defaultCatatan = "ATP disusun secara hierarkis dari kompetensi sederhana menuju kompleks sesuai karakteristik peserta didik jenjang {$jenjang} Kelas {$namaKelas} untuk panduan 1 tahun pelajaran.";

        if (! KlienGemini::aktif()) {
            return [
                'cp_fase' => $defaultCp,
                'catatan' => $defaultCatatan,
                'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jenjang),
            ];
        }

        $limit = 'atp-ai-user:'.$userId;
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            return [
                'cp_fase' => $defaultCp,
                'catatan' => $defaultCatatan.' (Batas kuota AI tercapai).',
                'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jenjang),
            ];
        }
        RateLimiter::hit($limit, 600);

        $promptSistem = 'Anda adalah pakar kurikulum dan pengembang kurikulum operasional resmi Kemendikbudristek dan Kemenag RI. '
            .'Tugas Anda adalah merancang Alur Tujuan Pembelajaran (ATP) Kurikulum Merdeka secara lengkap untuk satu tahun pelajaran penuh. '
            .'Prinsip utama penyusunan ATP: '
            .'1. Berdasarkan Capaian Pembelajaran (CP) resmi Kurikulum Merdeka untuk jenjang dan fase yang diminta. '
            .'2. Komponen wajib per butir: '
            .'   - elemen_cp: Elemen CP resmi (misal: Bilangan, Menyimak, Pemahaman IPAS, dsb) '
            .'   - capaian_pembelajaran: Deskripsi Capaian Pembelajaran pada elemen tersebut '
            .'   - tujuan_pembelajaran: Rumusan Tujuan Pembelajaran (TP) yang jelas menggunakan KKO terukur '
            .'   - materi_pembelajaran: Lingkup materi / konten pokok '
            .'   - alur_tujuan_pembelajaran: Kode tahapan alur (misal 7.1, 7.2 atau penjelasan tahapan alur) '
            .'   - alokasi_waktu_jp: Perkiraan alokasi waktu JP yang realistis '
            .'   - keterangan: Asesmen atau integrasi P5/P2RA '
            .'3. Alur tujuan pembelajaran HARUS disusun secara berurutan logis dari kompetensi yang paling sederhana (pemahaman dasar/mengingat) menuju kompetensi yang lebih kompleks (aplikasi, analisis, evaluasi, hingga kreasi/proyek). '
            .'4. ATP relevan dengan karakteristik peserta didik dan dapat digunakan langsung sebagai pedoman kegiatan pembelajaran selama 1 tahun. '
            .'5. Berikan capaian_pembelajaran_fase (rangkuman umum CP satu fase) dan catatan (rasional ATP dan karakteristik peserta didik). '
            .'Format keluaran WAJIB berupa JSON valid: '
            .'{ '
            .'"cp_fase": "Deskripsi umum CP fase", '
            .'"catatan": "Rasional dan catatan penyusunan ATP", '
            .'"items": [ '
            .'  { "urutan": 1, "elemen_cp": "...", "capaian_pembelajaran": "...", "tujuan_pembelajaran": "...", "materi_pembelajaran": "...", "alur_tujuan_pembelajaran": "...", "alokasi_waktu_jp": 8, "keterangan": "..." } '
            .'] '
            .'}';

        $promptUser = json_encode(array_filter([
            'jenjang' => $jenjang,
            'kelas' => $namaKelas,
            'fase' => $fase,
            'mata_pelajaran' => $namaMapel,
            'instruksi_khusus' => $instruksiKhusus,
        ]), JSON_THROW_ON_ERROR);

        try {
            $hasil = $this->klien->hasilkan(
                $promptSistem,
                $promptUser,
                ['responseMimeType' => 'application/json'],
                max(45, (int) config('services.gemini.timeout', 45)),
            );

            $data = json_decode($hasil, true);
            $rawItems = $data['items'] ?? (is_array($data) && array_is_list($data) ? $data : []);
            $cpFase = ! empty($data['cp_fase']) && is_string($data['cp_fase']) ? $data['cp_fase'] : $defaultCp;
            $catatan = ! empty($data['catatan']) && is_string($data['catatan']) ? $data['catatan'] : $defaultCatatan;

            if (empty($rawItems)) {
                return [
                    'cp_fase' => $cpFase,
                    'catatan' => $catatan,
                    'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jenjang),
                ];
            }

            $urutan = 1;
            $bersih = [];
            foreach ($rawItems as $row) {
                $bersih[] = [
                    'urutan' => $urutan++,
                    'elemen_cp' => (string) ($row['elemen_cp'] ?? 'Pemahaman Konsep'),
                    'capaian_pembelajaran' => (string) ($row['capaian_pembelajaran'] ?? 'Peserta didik memahami materi pokok.'),
                    'tujuan_pembelajaran' => (string) ($row['tujuan_pembelajaran'] ?? 'Peserta didik dapat memahami materi secara mendalam.'),
                    'materi_pembelajaran' => (string) ($row['materi_pembelajaran'] ?? 'Materi Pembelajaran Pokok'),
                    'alur_tujuan_pembelajaran' => (string) ($row['alur_tujuan_pembelajaran'] ?? ('Alur '.($urutan - 1))),
                    'alokasi_waktu_jp' => max(1, (int) ($row['alokasi_waktu_jp'] ?? 6)),
                    'keterangan' => ! empty($row['keterangan']) ? (string) $row['keterangan'] : 'Asesmen Formatif & Sumatif',
                ];
            }

            return [
                'cp_fase' => $cpFase,
                'catatan' => $catatan,
                'items' => $bersih,
            ];
        } catch (Throwable) {
            return [
                'cp_fase' => $defaultCp,
                'catatan' => $defaultCatatan,
                'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jenjang),
            ];
        }
    }

    /**
     * @param  list<AtpRow>  $items
     */
    public function terapkanKeAtp(AlurTujuanPembelajaran $atp, array $items): void
    {
        $atp->items()->delete();

        $urutan = 1;
        foreach ($items as $row) {
            $atp->items()->create([
                'urutan' => $urutan++,
                'elemen_cp' => $row['elemen_cp'],
                'capaian_pembelajaran' => $row['capaian_pembelajaran'],
                'tujuan_pembelajaran' => $row['tujuan_pembelajaran'],
                'materi_pembelajaran' => $row['materi_pembelajaran'],
                'alur_tujuan_pembelajaran' => $row['alur_tujuan_pembelajaran'],
                'alokasi_waktu_jp' => (int) ($row['alokasi_waktu_jp'] ?? 4),
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }
    }

    public function buatAtpLengkap(
        int $userId,
        Gtk $gtk,
        int $tahunAjaranId,
        int $kelasId,
        int $mataPelajaranId,
        string $jenjang = 'SMP',
        string $fase = 'D',
        ?string $instruksiKhusus = null
    ): AlurTujuanPembelajaran {
        $kelas = Kelas::query()->findOrFail($kelasId);
        $mapel = MataPelajaran::query()->findOrFail($mataPelajaranId);

        $hasil = $this->susunAtpLengkap(
            $userId,
            $mapel->nama,
            $kelas->nama,
            $fase,
            $jenjang,
            $instruksiKhusus
        );

        /** @var AlurTujuanPembelajaran $atp */
        $atp = AlurTujuanPembelajaran::query()->updateOrCreate(
            [
                'tahun_ajaran_id' => $tahunAjaranId,
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $mataPelajaranId,
            ],
            [
                'gtk_id' => $gtk->id,
                'jenjang' => $jenjang,
                'fase' => $fase,
                'capaian_pembelajaran_fase' => $hasil['cp_fase'],
                'catatan' => $hasil['catatan'],
            ]
        );

        $this->terapkanKeAtp($atp, $hasil['items']);

        return $atp->fresh();
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafMatematikaFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Bilangan',
                'capaian_pembelajaran' => 'Peserta didik dapat membaca, menulis, dan membandingkan bilangan bulat, bilangan rasional dan irasional, serta menggunakannya dalam menyelesaikan masalah kontekstual.',
                'tujuan_pembelajaran' => 'Memahami konsep bilangan bulat dan melakukan operasi aritmetika pada bilangan bulat.',
                'materi_pembelajaran' => 'Bilangan Bulat dan Operasi Hitung',
                'alur_tujuan_pembelajaran' => '7.1 (Dasar Operasi Bilangan Bulat)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Formatif awal & Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Bilangan',
                'capaian_pembelajaran' => 'Peserta didik dapat menerapkan operasi aritmetika pada bilangan pecahan dan desimal untuk menyelesaikan masalah sehari-hari.',
                'tujuan_pembelajaran' => 'Menyelesaikan permasalahan kontekstual melibatkan perbandingan rasio dan proporsi.',
                'materi_pembelajaran' => 'Bilangan Rasional & Rasio',
                'alur_tujuan_pembelajaran' => '7.2 (Penerapan Rasio dan Pecahan)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Aljabar',
                'capaian_pembelajaran' => 'Peserta didik dapat menyatakan suatu situasi ke dalam bentuk aljabar dan menggunakan sifat-sifat operasi untuk menyederhanakan bentuk aljabar.',
                'tujuan_pembelajaran' => 'Mengidentifikasi unsur-unsur aljabar dan menyederhanakan ekspresi aljabar linear.',
                'materi_pembelajaran' => 'Bentuk Aljabar Linear',
                'alur_tujuan_pembelajaran' => '7.3 (Transisi Aritmetika ke Aljabar)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 3 & Tengah Semester',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Aljabar',
                'capaian_pembelajaran' => 'Peserta didik dapat menyelesaikan persamaan dan pertidaksamaan linear satu variabel.',
                'tujuan_pembelajaran' => 'Memodelkan masalah dunia nyata ke dalam persamaan dan pertidaksamaan linear satu variabel dan menyelesaikannya.',
                'materi_pembelajaran' => 'Persamaan dan Pertidaksamaan Linear Satu Variabel (PLSV)',
                'alur_tujuan_pembelajaran' => '7.4 (Penyelesaian Masalah Model Aljabar)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 4 & Akhir Semester 1',
            ],
            [
                'urutan' => 5,
                'elemen_cp' => 'Pengukuran & Geometri',
                'capaian_pembelajaran' => 'Peserta didik dapat menjelaskan cara untuk menentukan luas lingkaran dan volume bangun ruang sisi datar.',
                'tujuan_pembelajaran' => 'Menghitung keliling dan luas bangun datar serta menyelesaikan masalah pengukuran geometris.',
                'materi_pembelajaran' => 'Bangun Datar dan Sudut',
                'alur_tujuan_pembelajaran' => '7.5 (Konsep Spasial dan Pengukuran Sudut/Bidang)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 5',
            ],
            [
                'urutan' => 6,
                'elemen_cp' => 'Geometri',
                'capaian_pembelajaran' => 'Peserta didik dapat membuat jaring-jaring bangun ruang dan menentukan luas permukaan serta volumenya.',
                'tujuan_pembelajaran' => 'Menganalisis sifat-sifat bangun ruang sisi datar, menghitung luas permukaan dan volume.',
                'materi_pembelajaran' => 'Bangun Ruang Sisi Datar (Kubus, Balok, Prisma, Limas)',
                'alur_tujuan_pembelajaran' => '7.6 (Pengukuran Dimensi Tiga)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 6 & STS 2',
            ],
            [
                'urutan' => 7,
                'elemen_cp' => 'Analisis Data & Peluang',
                'capaian_pembelajaran' => 'Peserta didik dapat merumuskan pertanyaan, mengumpulkan, menyajikan, dan menganalisis data menggunakan diagram.',
                'tujuan_pembelajaran' => 'Mengolah dan menyajikan data statistik ke dalam tabel dan diagram serta menarik kesimpulan valid.',
                'materi_pembelajaran' => 'Penyajian dan Analisis Data Statistik',
                'alur_tujuan_pembelajaran' => '7.7 (Literasi Data dan Penarikan Kesimpulan)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 7 & Proyek Mini Riset',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafBahasaIndonesiaFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Menyimak',
                'capaian_pembelajaran' => 'Peserta didik mampu menganalisis dan memaknai informasi berupa gagasan, pikiran, perasaan, pandangan, arahan atau pesan yang akurat dari berbagai jenis teks fiksi dan nonfiksi.',
                'tujuan_pembelajaran' => 'Menemukan informasi tersurat dan tersirat serta gagasan pokok dari teks deskripsi lisan/audio.',
                'materi_pembelajaran' => 'Teks Deskripsi Lisan dan Tulis',
                'alur_tujuan_pembelajaran' => '7.1 (Identifikasi dan Pemahaman Gagasan Deskriptif)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Formatif & Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Membaca dan Memirsa',
                'capaian_pembelajaran' => 'Peserta didik memahami informasi berupa gagasan, pikiran, pandangan, arahan atau pesan dari berbagai jenis teks untuk menemukan makna tersurat dan tersirat.',
                'tujuan_pembelajaran' => 'Menganalisis struktur dan ciri kebahasaan teks narasi/fantasi serta mengidentifikasi nilai karakter tokoh.',
                'materi_pembelajaran' => 'Teks Cerita Fantasi / Fiksi',
                'alur_tujuan_pembelajaran' => '7.2 (Apresiasi dan Analisis Unsur Intrinsik Cerita)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Berbicara dan Mempresentasikan',
                'capaian_pembelajaran' => 'Peserta didik mampu menyampaikan gagasan, pikiran, pandangan, arahan atau pesan untuk tujuan pengajuan usul, pemecahan masalah, dan pemberian solusi secara lisan.',
                'tujuan_pembelajaran' => 'Menyajikan langkah-langkah teks prosedur secara runtut, jelas, dan percaya diri secara lisan.',
                'materi_pembelajaran' => 'Teks Prosedur dan Panduan Praktis',
                'alur_tujuan_pembelajaran' => '7.3 (Penerapan Bahasa Instruksional Komunikatif)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 3 & Unjuk Kerja',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Menulis',
                'capaian_pembelajaran' => 'Peserta didik mampu menulis gagasan, pikiran, pandangan, arahan atau pesan tertulis untuk berbagai tujuan secara logis, kritis, dan kreatif.',
                'tujuan_pembelajaran' => 'Menyusun teks laporan hasil observasi (LHO) yang objektif berdasarkan fakta dengan kaidah kebahasaan baku.',
                'materi_pembelajaran' => 'Teks Laporan Hasil Observasi (LHO)',
                'alur_tujuan_pembelajaran' => '7.4 (Kepenulisan Faktual dan Objektif)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 4 & Portofolio LHO',
            ],
            [
                'urutan' => 5,
                'elemen_cp' => 'Membaca dan Memirsa',
                'capaian_pembelajaran' => 'Peserta didik menginterpretasi dan merefleksikan nilai-nilai kearifan lokal dalam puisi rakyat dan karya sastra.',
                'tujuan_pembelajaran' => 'Menganalisis pesan moral, rima, dan struktur pantun, syair, serta gurindam warisan nusantara.',
                'materi_pembelajaran' => 'Puisi Rakyat (Pantun, Syair, Gurindam)',
                'alur_tujuan_pembelajaran' => '7.5 (Kajian Estetika Budaya Bahasa)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif LM 5',
            ],
            [
                'urutan' => 6,
                'elemen_cp' => 'Menulis & Mempresentasikan',
                'capaian_pembelajaran' => 'Peserta didik mampu menulis tanggapan kritis terhadap buku fiksi dan nonfiksi serta mempresentasikannya.',
                'tujuan_pembelajaran' => 'Menulis resensi/ulasan buku secara komprehensif memuat kelebihan, kekurangan, dan rekomendasi pembaca.',
                'materi_pembelajaran' => 'Resensi dan Ulasan Literasi Buku',
                'alur_tujuan_pembelajaran' => '7.6 (Sintesis Kritis dan Apresiasi Literasi)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif Akhir & Proyek Literasi',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafIpaFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Pemahaman IPA',
                'capaian_pembelajaran' => 'Peserta didik memahami pengukuran besaran fisis, metode ilmiah, dan keselamatan kerja laboratorium.',
                'tujuan_pembelajaran' => 'Menerapkan metode ilmiah dan menggunakan alat ukur secara presisi dengan mematuhi keselamatan kerja.',
                'materi_pembelajaran' => 'Hakikat Sains, Pengukuran, dan Metode Ilmiah',
                'alur_tujuan_pembelajaran' => '7.1 (Keterampilan Proses Sains Dasar)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Praktikum & Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Pemahaman IPA',
                'capaian_pembelajaran' => 'Peserta didik memahami partikel zat, wujud zat, perubahan wujud zat, dan massa jenis.',
                'tujuan_pembelajaran' => 'Menganalisis sifat fisika dan kimia zat, perubahan wujud, serta menghitung massa jenis benda.',
                'materi_pembelajaran' => 'Zat dan Perubahannya (Wujud Zat & Massa Jenis)',
                'alur_tujuan_pembelajaran' => '7.2 (Struktur Materi dan Sifat Zat)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Pemahaman IPA',
                'capaian_pembelajaran' => 'Peserta didik memahami konsep suhu, kalor, perpindahan kalor, dan pemuaian zat dalam kehidupan sehari-hari.',
                'tujuan_pembelajaran' => 'Menghitung perpindahan kalor dan menganalisis mekanisme termoregulasi makhluk hidup.',
                'materi_pembelajaran' => 'Suhu, Kalor, dan Pemuaian',
                'alur_tujuan_pembelajaran' => '7.3 (Energi Termal dan Pengaruhnya)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 3 & STS 1',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Pemahaman IPA',
                'capaian_pembelajaran' => 'Peserta didik memahami konsep gerak lurus dan pengaruh gaya terhadap gerak berdasarkan Hukum Newton.',
                'tujuan_pembelajaran' => 'Menganalisis hubungan antara gaya, massa, dan percepatan (Hukum I, II, III Newton) pada fenomena gerak.',
                'materi_pembelajaran' => 'Gerak Lurus dan Gaya (Hukum Newton)',
                'alur_tujuan_pembelajaran' => '7.4 (Dinamika Gerak Benda)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 4 & Akhir Semester 1',
            ],
            [
                'urutan' => 5,
                'elemen_cp' => 'Pemahaman IPA',
                'capaian_pembelajaran' => 'Peserta didik memahami ciri-ciri makhluk hidup dan klasifikasi makhluk hidup dalam 5 kingdom.',
                'tujuan_pembelajaran' => 'Mengklasifikasikan keanekaragaman makhluk hidup berdasarkan kunci determinasi sederhana.',
                'materi_pembelajaran' => 'Klasifikasi Makhluk Hidup',
                'alur_tujuan_pembelajaran' => '7.5 (Keanekaragaman Hayati dan Taksonomi)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 5',
            ],
            [
                'urutan' => 6,
                'elemen_cp' => 'Keterampilan Proses & Pemahaman',
                'capaian_pembelajaran' => 'Peserta didik menganalisis interaksi antar komponen ekosistem serta dampak aktivitas manusia terhadap lingkungan.',
                'tujuan_pembelajaran' => 'Mendiagnosis rantai makanan, jaring-jaring makanan, dinamika populasi, dan merancang solusi pencemaran.',
                'materi_pembelajaran' => 'Ekologi dan Interaksi Ekosistem',
                'alur_tujuan_pembelajaran' => '7.6 (Keseimbangan Lingkungan dan Konservasi)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 6 & Proyek P5 Lingkungan',
            ],
            [
                'urutan' => 7,
                'elemen_cp' => 'Pemahaman IPA',
                'capaian_pembelajaran' => 'Peserta didik memahami struktur lapisan bumi, lempeng tektonik, bencana geologis, dan tata surya.',
                'tujuan_pembelajaran' => 'Menganalisis struktur bumi, mitigasi bencana gempa/gunung api, dan karakteristik anggota tata surya.',
                'materi_pembelajaran' => 'Bumi, Tata Surya, dan Mitigasi Bencana',
                'alur_tujuan_pembelajaran' => '7.7 (Bumi dan Antariksa Terpadu)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif Akhir Tahun',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafBahasaInggrisFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Listening and Speaking',
                'capaian_pembelajaran' => 'Students interact and exchange information, ideas, and opinions using simple spoken English in various everyday contexts.',
                'tujuan_pembelajaran' => 'Introduce oneself and others, share personal information, and express greetings and leave-taking.',
                'materi_pembelajaran' => 'Self-Introduction, Greetings, and Personal Details',
                'alur_tujuan_pembelajaran' => '7.1 (Interpersonal Basic Communication)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Speaking Performance & Formatif',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Reading and Viewing',
                'capaian_pembelajaran' => 'Students read and respond to familiar texts to identify main ideas and specific information.',
                'tujuan_pembelajaran' => 'Identify specific details, physical appearances, and characteristics in short descriptive texts.',
                'materi_pembelajaran' => 'Descriptive Text: Describing People and Family',
                'alur_tujuan_pembelajaran' => '7.2 (Descriptive Comprehension)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 1',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Writing and Presenting',
                'capaian_pembelajaran' => 'Students produce simple organized texts to communicate ideas and convey information.',
                'tujuan_pembelajaran' => 'Write short descriptive paragraphs about pets, favorite things, and places using simple present tense.',
                'materi_pembelajaran' => 'Writing Descriptive Texts (Simple Present Tense)',
                'alur_tujuan_pembelajaran' => '7.3 (Descriptive Composition)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 2 & Mid-term',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Listening and Speaking',
                'capaian_pembelajaran' => 'Students comprehend instructions and give directional or sequential guidance in daily interaction.',
                'tujuan_pembelajaran' => 'Follow and explain sequential recipe steps and technological operational instructions clearly.',
                'materi_pembelajaran' => 'Procedural Text (Food Recipe & Manuals)',
                'alur_tujuan_pembelajaran' => '7.4 (Instructional and Sequential Discourse)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 3',
            ],
            [
                'urutan' => 5,
                'elemen_cp' => 'Reading and Viewing',
                'capaian_pembelajaran' => 'Students locate, analyze, and interpret information from transactional and narrative texts.',
                'tujuan_pembelajaran' => 'Infer moral values, plot sequence, and character feelings from fables and folk tales.',
                'materi_pembelajaran' => 'Narrative Text (Fables & Folk Stories)',
                'alur_tujuan_pembelajaran' => '7.5 (Literary Narrative Analysis)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 4 & Project Reading',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafIpsFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Pemahaman Konsep',
                'capaian_pembelajaran' => 'Peserta didik memahami keberadaan diri dan keluarga serta pengaruh letak geografis Indonesia terhadap kehidupan.',
                'tujuan_pembelajaran' => 'Menganalisis lokasi tempat tinggal melalui pemahaman peta, letak astronomis, dan geografis Indonesia.',
                'materi_pembelajaran' => 'Keluarga Awal Kehidupan & Kondisi Geografis Indonesia',
                'alur_tujuan_pembelajaran' => '7.1 (Spasial Diri dan Lingkungan)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Keterampilan Proses',
                'capaian_pembelajaran' => 'Peserta didik menganalisis interaksi sosial, pembentukan norma, dan lembaga sosial masyarakat.',
                'tujuan_pembelajaran' => 'Mengevaluasi peran lembaga sosial dalam menciptakan keteraturan dan harmoni bermasyarakat.',
                'materi_pembelajaran' => 'Interaksi Sosial dan Lembaga Sosial',
                'alur_tujuan_pembelajaran' => '7.2 (Dinamika Sosiologis Masyarakat)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Pemahaman Konsep',
                'capaian_pembelajaran' => 'Peserta didik memahami kegiatan ekonomi, kelangkaan sumber daya, dan permintaan serta penawaran.',
                'tujuan_pembelajaran' => 'Mengidentifikasi kelangkaan, kebutuhan hidup manusia, dan mekanisme pasar (harga, permintaan, penawaran).',
                'materi_pembelajaran' => 'Aktivitas Memenuhi Kebutuhan Hidup (Ekonomi)',
                'alur_tujuan_pembelajaran' => '7.3 (Literasi Finansial dan Ekonomi)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif LM 3 & STS 1',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Pemahaman Konsep & Sejarah',
                'capaian_pembelajaran' => 'Peserta didik merekonstruksi perkembangan masyarakat Indonesia masa praaksara, Hindu-Buddha, dan Islam.',
                'tujuan_pembelajaran' => 'Menganalisis peninggalan budaya dan kontinuitas nilai kearifan lokal masa sejarah nusantara.',
                'materi_pembelajaran' => 'Peradaban Masa Praaksara, Hindu-Buddha, dan Islam',
                'alur_tujuan_pembelajaran' => '7.4 (Historisitas dan Kearifan Lokal Nusantara)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif Akhir Semester',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafPancasilaFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Pancasila',
                'capaian_pembelajaran' => 'Peserta didik menganalisis kronologis kelahiran, perumusan, hingga penetapan Pancasila sebagai dasar negara.',
                'tujuan_pembelajaran' => 'Meneladani komitmen kebangsaan para pendiri bangsa dalam perumusan Pancasila.',
                'materi_pembelajaran' => 'Sejarah Kelahiran dan Penetapan Pancasila',
                'alur_tujuan_pembelajaran' => '7.1 (Historisitas Nilai Ideologi Bangsa)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Undang-Undang Dasar NRI 1945',
                'capaian_pembelajaran' => 'Peserta didik memahami norma dan keadilan serta hak dan kewajiban warga negara.',
                'tujuan_pembelajaran' => 'Mempraktikkan perilaku taat norma hukum, kesusilaan, kesopanan, dan agama dalam kehidupan sehari-hari.',
                'materi_pembelajaran' => 'Norma, Keadilan, dan Penegakan Hukum',
                'alur_tujuan_pembelajaran' => '7.2 (Kesadaran Hukum dan Budaya Tertib)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Bhinneka Tunggal Ika',
                'capaian_pembelajaran' => 'Peserta didik menghargai keragaman suku, agama, ras, dan antargolongan dalam bingkai NKRI.',
                'tujuan_pembelajaran' => 'Membangun sikap inklusif, toleransi, dan menghargai keragaman budaya nusantara.',
                'materi_pembelajaran' => 'Keberagaman Suku, Agama, Ras, dan Antargolongan (SARA)',
                'alur_tujuan_pembelajaran' => '7.3 (Harmoni Sosial dan Kebhinnekaan)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif LM 3',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Negara Kesatuan Republik Indonesia',
                'capaian_pembelajaran' => 'Peserta didik memahami keutuhan NKRI dan peran aktif menjaga kedaulatan wilayah.',
                'tujuan_pembelajaran' => 'Merancang aksi nyata bela negara di lingkungan sekolah dan tempat tinggal.',
                'materi_pembelajaran' => 'Wilayah NKRI dan Semangat Persatuan Kebangsaan',
                'alur_tujuan_pembelajaran' => '7.4 (Komitmen Teritorial dan Cinta Tanah Air)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif Akhir',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafInformatikaFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Berpikir Komputasional (BK)',
                'capaian_pembelajaran' => 'Peserta didik mampu menerapkan berpikir komputasional untuk memecahkan persoalan komputasi sehari-hari.',
                'tujuan_pembelajaran' => 'Menerapkan dekomposisi, pengenalan pola, abstraksi, dan algoritma untuk penyelesaian masalah.',
                'materi_pembelajaran' => 'Fondasi Berpikir Komputasional (BK)',
                'alur_tujuan_pembelajaran' => '7.1 (Logika Algoritmik Dasar)',
                'alokasi_waktu_jp' => 10,
                'keterangan' => 'Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Teknologi Informasi & Komunikasi (TIK)',
                'capaian_pembelajaran' => 'Peserta didik mampu memanfaatkan aplikasi perkantoran untuk pengolahan dokumen dan lembar kerja.',
                'tujuan_pembelajaran' => 'Membuat dokumen laporan, mengolah data dengan rumus spreadsheet, dan membuat presentasi multimedia.',
                'materi_pembelajaran' => 'Aplikasi Pengolah Kata, Lembar Sebar, dan Presentasi',
                'alur_tujuan_pembelajaran' => '7.2 (Produktivitas Digital Terintegrasi)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Praktikum & Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Sistem Komputer (SK) & Jaringan Komputer',
                'capaian_pembelajaran' => 'Peserta didik memahami komponen perangkat keras, perangkat lunak, dan interaksi jaringan internet.',
                'tujuan_pembelajaran' => 'Mengidentifikasi arsitektur komputer dan menerapkan keamanan koneksi internet sederhana.',
                'materi_pembelajaran' => 'Perangkat Keras, Perangkat Lunak, dan Internet',
                'alur_tujuan_pembelajaran' => '7.3 (Infrastruktur dan Keamanan Jaringan)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif LM 3',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Algoritma & Pemrograman (AP)',
                'capaian_pembelajaran' => 'Peserta didik mampu membuat program visual sederhana dengan konsep perulangan dan percabangan.',
                'tujuan_pembelajaran' => 'Merancang animasi interaktif atau game edukasi sederhana menggunakan pemrograman blok (Scratch).',
                'materi_pembelajaran' => 'Pemrograman Visual Berbasis Blok',
                'alur_tujuan_pembelajaran' => '7.4 (Kreativitas Coding Komputasional)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Proyek Pemrograman',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafPaiFaseD(string $namaMapel): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Al-Qur\'an dan Hadis',
                'capaian_pembelajaran' => 'Peserta didik memahami ayat-ayat Al-Qur\'an dan hadis tentang ikhtiar, tawakal, toleransi, dan ilmu pengetahuan.',
                'tujuan_pembelajaran' => 'Membaca tartil, menerjemahkan, dan mengamalkan pesan ayat Al-Qur\'an tentang menuntut ilmu.',
                'materi_pembelajaran' => 'Ayat Al-Qur\'an dan Hadis Keutamaan Ilmu',
                'alur_tujuan_pembelajaran' => '7.1 (Fondasi Tekstual dan Tadabbur Ayat)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Praktik Baca & Sumatif LM 1',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Akidah',
                'capaian_pembelajaran' => 'Peserta didik mendalami rukun iman, sifat-sifat Allah, serta asmaul husna.',
                'tujuan_pembelajaran' => 'Menginternalisasi nilai asmaul husna (Al-Alim, Al-Khabir, As-Sami\', Al-Bashir) dalam akhlak pribadi.',
                'materi_pembelajaran' => 'Asmaul Husna dan Peneguhan Akidah Tauhid',
                'alur_tujuan_pembelajaran' => '7.2 (Internalisasi Akidah dan Karakter)',
                'alokasi_waktu_jp' => 10,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Akhlak',
                'capaian_pembelajaran' => 'Peserta didik membiasakan akhlak terpuji kepada Allah, sesama manusia, dan alam semesta.',
                'tujuan_pembelajaran' => 'Mempraktikkan adab kepada orang tua, guru, dan teman serta menjauhi sifat tercela (ghibah, fitnah).',
                'materi_pembelajaran' => 'Birrul Walidain dan Akhlak Mahmudah',
                'alur_tujuan_pembelajaran' => '7.3 (Penerapan Moralitas Islami)',
                'alokasi_waktu_jp' => 10,
                'keterangan' => 'Sumatif LM 3',
            ],
            [
                'urutan' => 4,
                'elemen_cp' => 'Fikih / Ibadah',
                'capaian_pembelajaran' => 'Peserta didik memahami ketentuan thaharah, salat wajib, salat sunah, dan sujud.',
                'tujuan_pembelajaran' => 'Mempraktikkan tata cara bersuci (thaharah) dari hadas dan najis serta pelaksanaan salat berjamaah.',
                'materi_pembelajaran' => 'Thaharah dan Fikih Ibadah Salat',
                'alur_tujuan_pembelajaran' => '7.4 (Kepatuhan Syariat dan Praktik Ibadah)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Praktik Ibadah & Sumatif LM 4',
            ],
            [
                'urutan' => 5,
                'elemen_cp' => 'Sejarah Peradaban Islam (SPI)',
                'capaian_pembelajaran' => 'Peserta didik mengapresiasi dakwah Nabi Muhammad SAW dan kepemimpinan Khulafaur Rasyidin.',
                'tujuan_pembelajaran' => 'Meneladani strategi dakwah Nabi Muhammad SAW periode Mekah dan Madinah yang penuh keteladanan.',
                'materi_pembelajaran' => 'Sirah Nabawiyah dan Kepemimpinan Islam',
                'alur_tujuan_pembelajaran' => '7.5 (Keteladanan Sejarah dan Peradaban)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif Akhir',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafPjokFaseD(): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Keterampilan Gerak',
                'capaian_pembelajaran' => 'Peserta didik mempraktikkan keterampilan gerak spesifik dalam berbagai permainan bola besar.',
                'tujuan_pembelajaran' => 'Mempraktikkan variasi passing, dribbling, dan shooting pada permainan sepak bola atau bola basket.',
                'materi_pembelajaran' => 'Permainan Bola Besar (Sepak Bola / Basket / Voli)',
                'alur_tujuan_pembelajaran' => '7.1 (Keterampilan Motorik Bola Besar)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Praktik Keterampilan',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Keterampilan Gerak',
                'capaian_pembelajaran' => 'Peserta didik mempraktikkan gerak spesifik atletik (lari, lompat, tolak).',
                'tujuan_pembelajaran' => 'Melakukan teknik lari jarak pendek (sprint) dan lompat jauh gaya jongkok dengan koordinasi tubuh baik.',
                'materi_pembelajaran' => 'Aktivitas Atletik (Lari Cepat & Lompat Jauh)',
                'alur_tujuan_pembelajaran' => '7.2 (Pengembangan Motorik Dasar Atletik)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Sumatif LM 2',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Pemanfaatan Gerak',
                'capaian_pembelajaran' => 'Peserta didik memahami konsep kebugaran jasmani terkait kesehatan (daya tahan, kekuatan, kelenturan).',
                'tujuan_pembelajaran' => 'Merancang program latihan kebugaran jasmani sederhana dan menghitung denyut nadi latihan.',
                'materi_pembelajaran' => 'Latihan Kebugaran Jasmani dan Gaya Hidup Sehat',
                'alur_tujuan_pembelajaran' => '7.3 (Kebugaran Fisik dan Pola Hidup Aktif)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Tes Kebugaran Jasmani',
            ],
        ];
    }

    /**
     * @return list<AtpRow>
     */
    protected function drafGenerik(string $namaMapel, string $fase): array
    {
        return [
            [
                'urutan' => 1,
                'elemen_cp' => 'Fondasi Pengetahuan',
                'capaian_pembelajaran' => "Peserta didik memahami konsep dasar, terminologi, dan prinsip kunci mata pelajaran {$namaMapel} Fase {$fase}.",
                'tujuan_pembelajaran' => "Mengidentifikasi dan mendeskripsikan ruang lingkup dasar {$namaMapel} secara sistematis.",
                'materi_pembelajaran' => "Pengenalan dan Konsep Dasar {$namaMapel}",
                'alur_tujuan_pembelajaran' => 'Alur 1 (Pemahaman Dasar / Tahap Fondasi)',
                'alokasi_waktu_jp' => 12,
                'keterangan' => 'Formatif & Sumatif Awal',
            ],
            [
                'urutan' => 2,
                'elemen_cp' => 'Penerapan & Keterampilan Proses',
                'capaian_pembelajaran' => "Peserta didik mampu menerapkan konsep {$namaMapel} dalam penyelesaian masalah kontekstual.",
                'tujuan_pembelajaran' => "Mengaplikasikan prinsip-prinsip operasional {$namaMapel} untuk menganalisis studi kasus nyata.",
                'materi_pembelajaran' => 'Aplikasi Praktis dan Analisis Masalah',
                'alur_tujuan_pembelajaran' => 'Alur 2 (Penerapan dan Analisis Terstruktur)',
                'alokasi_waktu_jp' => 14,
                'keterangan' => 'Sumatif Tengah Semester',
            ],
            [
                'urutan' => 3,
                'elemen_cp' => 'Evaluasi & Kreasi Mandiri',
                'capaian_pembelajaran' => "Peserta didik mampu menghasilkan karya, gagasan solutif, atau laporan proyek komprehensif bidang {$namaMapel}.",
                'tujuan_pembelajaran' => "Mengevaluasi hasil kerja serta merancang karya inovatif berbasis profil pelajar Pancasila.",
                'materi_pembelajaran' => 'Proyek Kreatif dan Evaluasi Komprehensif',
                'alur_tujuan_pembelajaran' => 'Alur 3 (Sintesis Kompleks dan Proyek Portofolio)',
                'alokasi_waktu_jp' => 16,
                'keterangan' => 'Sumatif Akhir & Gelar Karya',
            ],
        ];
    }
}
