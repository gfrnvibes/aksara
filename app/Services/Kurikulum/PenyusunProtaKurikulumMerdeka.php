<?php

namespace App\Services\Kurikulum;

use App\Enums\Semester;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\ProgramTahunan;
use App\Models\ProgramTahunanItem;
use App\Services\Gemini\KlienGemini;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * @phpstan-type ProtaRow array{semester: int, urutan: int, elemen_cp: string, materi_pokok: string, tujuan_pembelajaran: string, alokasi_waktu_jp: int, minggu_efektif: int, keterangan: string|null}
 */
class PenyusunProtaKurikulumMerdeka
{
    public function __construct(protected ?KlienGemini $klien = null)
    {
        $this->klien ??= app(KlienGemini::class);
    }

    /**
     * Determine recommended Fase based on Tingkat kelas.
     */
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
     * Provide realistic default Kurikulum Merdeka template rows for a subject.
     *
     * @return list<ProtaRow>
     */
    public function dapatkanDrafStandar(string $namaMapel, string $fase = 'D', int $jpPerMinggu = 3): array
    {
        $namaLower = mb_strtolower($namaMapel);

        if (str_contains($namaLower, 'matematik')) {
            return $this->drafMatematikaFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'indonesia')) {
            return $this->drafBahasaIndonesiaFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'inggris')) {
            return $this->drafBahasaInggrisFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'ipa') || str_contains($namaLower, 'alam')) {
            return $this->drafIpaFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'ips') || str_contains($namaLower, 'sosial')) {
            return $this->drafIpsFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'pancasila') || str_contains($namaLower, 'pkn')) {
            return $this->drafPancasilaFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'informatik') || str_contains($namaLower, 'tik')) {
            return $this->drafInformatikaFaseD($jpPerMinggu);
        }

        if (str_contains($namaLower, 'agama') || str_contains($namaLower, 'pai') || str_contains($namaLower, 'akidah') || str_contains($namaLower, 'qur') || str_contains($namaLower, 'fiqih') || str_contains($namaLower, 'ski')) {
            return $this->drafPaiFaseD($namaMapel, $jpPerMinggu);
        }

        if (str_contains($namaLower, 'pjok') || str_contains($namaLower, 'olahraga') || str_contains($namaLower, 'penjas')) {
            return $this->drafPjokFaseD($jpPerMinggu);
        }

        return $this->drafGenerik($namaMapel, $fase, $jpPerMinggu);
    }

    /**
     * Generate complete PROTA (catatan + list of items) directly from start to finish.
     *
     * @return array{catatan: string, items: list<ProtaRow>}
     */
    public function susunProtaLengkap(
        int $userId,
        string $namaMapel,
        string $namaKelas,
        string $fase,
        int $jpPerMinggu = 3,
        ?string $instruksiKhusus = null
    ): array {
        if (! KlienGemini::aktif()) {
            return [
                'catatan' => "Program Tahunan disusun berdasarkan Capaian Pembelajaran resmi Kurikulum Merdeka untuk mata pelajaran {$namaMapel} Fase {$fase} (Kelas {$namaKelas}), dengan total alokasi waktu berbasis kalender pendidikan efektif.",
                'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jpPerMinggu),
            ];
        }

        $limit = 'prota-ai-user:'.$userId;
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            return [
                'catatan' => "Program Tahunan disusun berdasarkan standar Kurikulum Merdeka untuk {$namaMapel} Fase {$fase} (Batas kuota AI sementara tercapai).",
                'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jpPerMinggu),
            ];
        }
        RateLimiter::hit($limit, 600);

        $promptSistem = 'Anda adalah konsultan dan ahli perencanaan pembelajaran Kurikulum Merdeka resmi Kemendikbudristek dan Kemenag RI. '
            .'Tugas Anda adalah merancang Program Tahunan (PROTA) secara lengkap dan utuh dari awal sampai akhir untuk satu tahun pelajaran. '
            .'Aturan Kurikulum Merdeka: '
            .'1. Kalender pendidikan Indonesia mengalokasikan sekitar 18 minggu efektif di Semester 1 (Ganjil) dan sekitar 16 minggu efektif di Semester 2 (Genap). '
            .'2. Alokasi waktu jam pelajaran (JP) untuk setiap materi harus realistis sesuai JP per minggu yang diberikan. '
            .'3. Butir materi harus mencakup seluruh Capaian Pembelajaran (CP) fase tersebut secara berurutan logis. '
            .'4. Rumuskan Tujuan Pembelajaran (TP) yang terukur menggunakan Kata Kerja Operasional (KKO) Taksonomi Bloom revisi. '
            .'5. Keterangan berisi status evaluasi pembelajaran: Formatif, Sumatif Lingkup Materi 1, Sumatif Lingkup Materi 2, dst., Sumatif Akhir Semester, Proyek P5/P2RA, dan Cadangan. '
            .'6. Tuliskan ringkasan "catatan" perencanaan tahunan (2-4 kalimat) yang merangkum fokus pembelajaran, pendekatan kontekstual, dan penguatan karakter Profil Pelajar Pancasila / Rahmatan Lil Alamin. '
            .'Keluarkan format JSON murni dengan skema: '
            .'{ '
            .'"catatan": "string penjelasan perencanaan tahunan", '
            .'"items": [ '
            .'  { "semester": 1, "elemen_cp": "string", "materi_pokok": "string", "tujuan_pembelajaran": "string", "alokasi_waktu_jp": 16, "minggu_efektif": 4, "keterangan": "string" } '
            .'] '
            .'}';

        $promptUser = json_encode(array_filter([
            'mata_pelajaran' => $namaMapel,
            'kelas' => $namaKelas,
            'fase' => $fase,
            'jp_per_minggu' => $jpPerMinggu,
            'minggu_efektif_semester_1' => 18,
            'minggu_efektif_semester_2' => 16,
            'instruksi_khusus' => $instruksiKhusus,
        ]), JSON_THROW_ON_ERROR);

        try {
            $hasil = $this->klien->hasilkan(
                $promptSistem,
                $promptUser,
                [
                    'responseMimeType' => 'application/json',
                ],
                max(45, (int) config('services.gemini.timeout', 45)),
            );

            $data = json_decode($hasil, true);
            $rawItems = $data['items'] ?? (is_array($data) && array_is_list($data) ? $data : []);
            $catatan = ! empty($data['catatan']) && is_string($data['catatan'])
                ? $data['catatan']
                : "Program Tahunan disusun dengan pendekatan Kurikulum Merdeka untuk mata pelajaran {$namaMapel} Fase {$fase}.";

            if (empty($rawItems)) {
                return [
                    'catatan' => $catatan,
                    'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jpPerMinggu),
                ];
            }

            $urutan1 = 1;
            $urutan2 = 1;
            $bersih = [];

            foreach ($rawItems as $row) {
                $sem = (int) ($row['semester'] ?? 1);
                $sem = in_array($sem, [1, 2], true) ? $sem : 1;
                $urutan = $sem === 1 ? $urutan1++ : $urutan2++;

                $bersih[] = [
                    'semester' => $sem,
                    'urutan' => $urutan,
                    'elemen_cp' => (string) ($row['elemen_cp'] ?? 'Pemahaman Materi'),
                    'materi_pokok' => (string) ($row['materi_pokok'] ?? 'Materi Pembelajaran'),
                    'tujuan_pembelajaran' => (string) ($row['tujuan_pembelajaran'] ?? 'Peserta didik memahami konsep pokok.'),
                    'alokasi_waktu_jp' => max(1, (int) ($row['alokasi_waktu_jp'] ?? ($jpPerMinggu * 2))),
                    'minggu_efektif' => max(1, (int) ($row['minggu_efektif'] ?? 2)),
                    'keterangan' => ! empty($row['keterangan']) ? (string) $row['keterangan'] : 'Formatif & Sumatif Lingkup Materi',
                ];
            }

            return [
                'catatan' => $catatan,
                'items' => $bersih,
            ];
        } catch (Throwable) {
            return [
                'catatan' => "Program Tahunan disusun berdasarkan Capaian Pembelajaran resmi Kurikulum Merdeka untuk {$namaMapel} Fase {$fase}.",
                'items' => $this->dapatkanDrafStandar($namaMapel, $fase, $jpPerMinggu),
            ];
        }
    }

    /**
     * Create or update a complete PROTA model with AI in one call.
     */
    public function buatProtaLengkap(
        int $userId,
        Gtk $gtk,
        int $tahunAjaranId,
        int $kelasId,
        int $mataPelajaranId,
        string $fase,
        int $jpPerMinggu = 3,
        ?string $instruksiKhusus = null
    ): ProgramTahunan {
        $kelas = Kelas::query()->findOrFail($kelasId);
        $mapel = MataPelajaran::query()->findOrFail($mataPelajaranId);

        $hasil = $this->susunProtaLengkap(
            $userId,
            $mapel->nama,
            $kelas->nama,
            $fase,
            $jpPerMinggu,
            $instruksiKhusus
        );

        $prota = ProgramTahunan::query()->updateOrCreate(
            [
                'tahun_ajaran_id' => $tahunAjaranId,
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $mataPelajaranId,
            ],
            [
                'gtk_id' => $gtk->id,
                'fase' => $fase,
                'catatan' => $hasil['catatan'],
            ]
        );

        $this->terapkanKeProta($prota, $hasil['items']);

        return $prota;
    }

    /**
     * Generate PROTA items with Gemini AI as Kurikulum Merdeka expert.
     *
     * @return list<ProtaRow>
     */
    public function susunDenganGemini(int $userId, string $namaMapel, string $namaKelas, string $fase, int $jpPerMinggu = 3, ?string $instruksiKhusus = null): array
    {
        return $this->susunProtaLengkap($userId, $namaMapel, $namaKelas, $fase, $jpPerMinggu, $instruksiKhusus)['items'];
    }

    /**
     * Apply a list of items to a ProgramTahunan model.
     *
     * @param  list<ProtaRow>  $items
     */
    public function terapkanKeProta(ProgramTahunan $prota, array $items): void
    {
        $prota->items()->delete();

        foreach ($items as $item) {
            ProgramTahunanItem::query()->create([
                'program_tahunan_id' => $prota->id,
                'semester' => $item['semester'],
                'urutan' => $item['urutan'],
                'elemen_cp' => $item['elemen_cp'],
                'materi_pokok' => $item['materi_pokok'],
                'tujuan_pembelajaran' => $item['tujuan_pembelajaran'],
                'alokasi_waktu_jp' => $item['alokasi_waktu_jp'],
                'minggu_efektif' => $item['minggu_efektif'],
                'keterangan' => $item['keterangan'] ?? null,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Built-in Kurikulum Merdeka Subject Templates (Accurate & Realistic)
    // -------------------------------------------------------------------------

    /** @return list<ProtaRow> */
    protected function drafMatematikaFaseD(int $jp): array
    {
        return [
            // Semester 1 (18 minggu x misal 4 JP = 72 JP)
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Bilangan',
                'materi_pokok' => 'Bilangan Bulat dan Operasi Hitung',
                'tujuan_pembelajaran' => 'Membaca, menulis, membandingkan, serta melakukan operasi penjumlahan, pengurangan, perkalian, dan pembagian bilangan bulat pada masalah kontekstual.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Bilangan',
                'materi_pokok' => 'Bilangan Rasional (Pecahan dan Desimal)',
                'tujuan_pembelajaran' => 'Membandingkan, mengurutkan, dan mengoperasikan bilangan pecahan serta desimal dalam pemecahan masalah sehari-hari.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Aljabar',
                'materi_pokok' => 'Bentuk Aljabar dan Operasinya',
                'tujuan_pembelajaran' => 'Menyatakan situasi ke dalam bentuk aljabar, mengidentifikasi suku sejenis, serta melakukan operasi aljabar sederhana.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 1, 'urutan' => 4,
                'elemen_cp' => 'Aljabar',
                'materi_pokok' => 'Persamaan dan Pertidaksamaan Linear Satu Variabel',
                'tujuan_pembelajaran' => 'Menemukan konsep dan menyelesaikan permasalahan kehidupan nyata yang berkaitan dengan PLSV dan PtLSV.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 1, 'urutan' => 5,
                'elemen_cp' => 'Aljabar & Pengukuran',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Semester (SAS)',
                'tujuan_pembelajaran' => 'Mengevaluasi penguasaan materi semester 1 dan pengayaan/remedial terstruktur.',
                'alokasi_waktu_jp' => 8, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Sumatif Akhir Semester 1',
            ],

            // Semester 2 (16 minggu x 4 JP = 64 JP)
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Pengukuran & Aljabar',
                'materi_pokok' => 'Perbandingan (Rasio dan Proporsi)',
                'tujuan_pembelajaran' => 'Memahami perbandingan senilai dan berbalik nilai serta menerapkannya pada konsep skala dan peta.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Geometri',
                'materi_pokok' => 'Garis dan Sudut',
                'tujuan_pembelajaran' => 'Mengidentifikasi kedudukan dua garis, jenis-jenis sudut, serta sifat sudut antardua garis sejajar yang dipotong garis lain.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 6',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Geometri',
                'materi_pokok' => 'Bangun Datar (Segitiga dan Segiempat)',
                'tujuan_pembelajaran' => 'Menganalisis sifat, menghitung keliling dan luas bangun datar segitiga serta segiempat dalam situasi konkret.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 7',
            ],
            [
                'semester' => 2, 'urutan' => 4,
                'elemen_cp' => 'Analisis Data dan Peluang',
                'materi_pokok' => 'Penyajian dan Pengolahan Data',
                'tujuan_pembelajaran' => 'Mengumpulkan, mengorganisasi, menyajikan data dalam berbagai diagram, serta menentukan ukuran pemusatan data (mean, median, modus).',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 8',
            ],
            [
                'semester' => 2, 'urutan' => 5,
                'elemen_cp' => 'Analisis Data & Geometri',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Tahun (SAT)',
                'tujuan_pembelajaran' => 'Mengevaluasi penguasaan materi semester 2 dan capaian kompetensi tahunan.',
                'alokasi_waktu_jp' => 8, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Sumatif Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafBahasaIndonesiaFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Membaca dan Memirsa',
                'materi_pokok' => 'Teks Deskripsi (Menjelajah Keindahan Nusantara)',
                'tujuan_pembelajaran' => 'Menemukan informasi tersurat dan tersirat, menganalisis struktur serta ciri kebahasaan dalam teks deskripsi.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Menulis & Mempresentasikan',
                'materi_pokok' => 'Mencipta Teks Puisi Rakyat (Pantun, Gurindam, Syair)',
                'tujuan_pembelajaran' => 'Menganalisis nilai luhur dan unsur kebahasaan puisi rakyat serta mengekspresikan gagasan dan nasihat kebaikan ke dalam bait pantun.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Menyimak, Membaca dan Menulis',
                'materi_pokok' => 'Teks Cerita Narasi Fantasi',
                'tujuan_pembelajaran' => 'Mengidentifikasi alur tokoh dan tema cerita fantasi serta memproduksi tulisan cerita imajinatif dengan runtut.',
                'alokasi_waktu_jp' => 24, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 1, 'urutan' => 4,
                'elemen_cp' => 'Membaca dan Menulis',
                'materi_pokok' => 'Teks Prosedur (Langkah Tepat Berkreasi)',
                'tujuan_pembelajaran' => 'Menelaah urutan langkah, pilihan kata perintah, dan merancang teks prosedur panduan berkarya secara logis.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 1, 'urutan' => 5,
                'elemen_cp' => 'Semua Elemen',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Semester (SAS)',
                'tujuan_pembelajaran' => 'Mengukur pemahaman literasi membaca dan menulis materi semester 1.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Akhir Semester',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Membaca dan Memirsa',
                'materi_pokok' => 'Teks Berita dan Media Digital',
                'tujuan_pembelajaran' => 'Menganalisis fakta dan opini, mengenali struktur berita (5W+1H), dan menyaring informasi hoaks di era digital.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Menulis & Berbicara',
                'materi_pokok' => 'Teks Tanggapan Kritis terhadap Buku Fiksi dan Nonfiksi',
                'tujuan_pembelajaran' => 'Menyampaikan resensi dan tanggapan kritis terhadap kelebihan dan kelemahan suatu karya secara objektif dan santun.',
                'alokasi_waktu_jp' => 24, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 6',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Menulis dan Membaca',
                'materi_pokok' => 'Surat Pribadi dan Surat Dinas Resmi',
                'tujuan_pembelajaran' => 'Membedakan etika dan format surat pribadi dengan surat resmi serta menyusun surat resmi madrasah/organisasi.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 7',
            ],
            [
                'semester' => 2, 'urutan' => 4,
                'elemen_cp' => 'Semua Elemen',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Tahun (SAT)',
                'tujuan_pembelajaran' => 'Mengukur ketercapaian kompetensi berbahasa dan bersastra satu tahun pelajaran.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafIpaFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Pemahaman IPA & Keterampilan Proses',
                'materi_pokok' => 'Hakikat Sains, Metode Ilmiah, dan Keselamatan Laboratorium',
                'tujuan_pembelajaran' => 'Merancang dan melakukan penyelidikan ilmiah sederhana serta menerapkan prosedur keselamatan kerja di laboratorium IPA.',
                'alokasi_waktu_jp' => 15, 'minggu_efektif' => 3,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Pemahaman IPA',
                'materi_pokok' => 'Zat, Wujud Zat, dan Perubahannya',
                'tujuan_pembelajaran' => 'Menganalisis sifat fisika dan kimia zat, perubahan wujud, serta menghitung massa jenis benda.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Pemahaman IPA',
                'materi_pokok' => 'Suhu, Kalor, dan Pemuaian',
                'tujuan_pembelajaran' => 'Menjelaskan konsep suhu, prinsip pemuaian benda, serta menghitung kalor pada perpindahan kalor secara konduksi, konveksi, dan radiasi.',
                'alokasi_waktu_jp' => 25, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 1, 'urutan' => 4,
                'elemen_cp' => 'Pemahaman IPA & Keterampilan Proses',
                'materi_pokok' => 'Gerak Lurus dan Gaya',
                'tujuan_pembelajaran' => 'Menganalisis konsep GLB, GLBB, resultan gaya, serta penerapan Hukum I, II, dan III Newton dalam kehidupan.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 1, 'urutan' => 5,
                'elemen_cp' => 'Pemahaman IPA',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Semester (SAS)',
                'tujuan_pembelajaran' => 'Mengevaluasi penguasaan konsep IPA fisik dan keterampilan proses semester 1.',
                'alokasi_waktu_jp' => 10, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Akhir Semester 1',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Pemahaman IPA',
                'materi_pokok' => 'Klasifikasi Makhluk Hidup',
                'tujuan_pembelajaran' => 'Membedakan makhluk hidup dan benda tak hidup, serta mengklasifikasikan organisme menggunakan kunci determinasi sederhana.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Pemahaman IPA & Keterampilan Proses',
                'materi_pokok' => 'Ekologi, Ekosistem, dan Interaksi Lingkungan',
                'tujuan_pembelajaran' => 'Menganalisis komponen ekosistem, jaring-jaring makanan, dinamika populasi, dan dampak aktivitas manusia terhadap kelestarian lingkungan.',
                'alokasi_waktu_jp' => 25, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 6',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Pemahaman IPA',
                'materi_pokok' => 'Bumi, Tata Surya, dan Perubahan Iklim',
                'tujuan_pembelajaran' => 'Menjelaskan sistem tata surya, gerak rotasi/revolusi bumi, fenomena gerhana, serta aksi mitigasi perubahan iklim global.',
                'alokasi_waktu_jp' => 25, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 7',
            ],
            [
                'semester' => 2, 'urutan' => 4,
                'elemen_cp' => 'Pemahaman IPA',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Tahun (SAT)',
                'tujuan_pembelajaran' => 'Mengevaluasi penguasaan konsep IPA hayati dan kebumian secara komprehensif.',
                'alokasi_waktu_jp' => 10, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafIpsFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Pemahaman Konsep',
                'materi_pokok' => 'Keluarga dan Pembentukan Karakter Sosial',
                'tujuan_pembelajaran' => 'Menjelaskan peran keluarga dalam proses sosialisasi, norma sosial, dan pembentukan jati diri individu di lingkungan masyarakat.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Pemahaman Konsep',
                'materi_pokok' => 'Konektivitas Antarruang dan Potensi Geografis Indonesia',
                'tujuan_pembelajaran' => 'Menganalisis posisi strategis astronomis dan geografis Indonesia serta persebaran sumber daya alam nusantara.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Keterampilan Proses',
                'materi_pokok' => 'Kebutuhan Manusia, Kelangkaan, dan Tindakan Ekonomi',
                'tujuan_pembelajaran' => 'Mengidentifikasi jenis kebutuhan manusia, skala prioritas, dan konsep kelangkaan sebagai pendorong kegiatan ekonomi bijak.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 1, 'urutan' => 4,
                'elemen_cp' => 'Pemahaman Konsep',
                'materi_pokok' => 'Kehidupan Masyarakat Masa Praaksara',
                'tujuan_pembelajaran' => 'Menjelaskan corak kehidupan manusia zaman berburu, bercocok tanam, dan perundagian serta peninggalan budayanya.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 4 / SAS',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Pemahaman Konsep',
                'materi_pokok' => 'Keragaman Sosial Budaya Masyarakat Indonesia',
                'tujuan_pembelajaran' => 'Menghargai keragaman suku, bahasa, adat, dan agama sebagai modal sosial integrasi bangsa Indonesia.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Keterampilan Proses',
                'materi_pokok' => 'Pasar, Harga, Permintaan, dan Penawaran',
                'tujuan_pembelajaran' => 'Menganalisis mekanisme terbentuknya harga pasar, peranan produsen, konsumen, dan distributor dalam perekonomian lokal.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 6',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Pemahaman Konsep',
                'materi_pokok' => 'Jalur Perdagangan Kuno dan Kerajaan Hindu-Buddha-Islam di Nusantara',
                'tujuan_pembelajaran' => 'Menelusuri sejarah keemasan kerajaan maritim nusantara dan akulturasi budaya Islam, Hindu, dan Buddha.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 7',
            ],
            [
                'semester' => 2, 'urutan' => 4,
                'elemen_cp' => 'Keterampilan Proses',
                'materi_pokok' => 'Literasi Keuangan dan Perencanaan Finansial Remaja',
                'tujuan_pembelajaran' => 'Menyusun anggaran pribadi, membedakan kebutuhan primer vs sekunder, dan membiasakan investasi masa depan.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 2,
                'keterangan' => 'Sumatif Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafBahasaInggrisFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Menyimak - Berbicara',
                'materi_pokok' => 'Greeting, Introducing Oneself and Others',
                'tujuan_pembelajaran' => 'Menggunakan ungkapan sapaan, perkenalan diri, dan menanyakan kabar dalam percakapan interpersonal santun.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Membaca - Memirsa',
                'materi_pokok' => 'Descriptive Text: People, Animals, and Places',
                'tujuan_pembelajaran' => 'Mengidentifikasi gagasan utama dan informasi rinci dari teks deskriptif bergambar tentang orang dan tempat favorit.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Menulis - Mempresentasikan',
                'materi_pokok' => 'Writing Descriptive Paragraphs',
                'tujuan_pembelajaran' => 'Menulis teks deskriptif pendek dengan struktur organisasi dan kosa kata kata sifat (adjectives) yang tepat.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 1, 'urutan' => 4,
                'elemen_cp' => 'Semua Elemen',
                'materi_pokok' => 'Review & Asesmen Sumatif Akhir Semester (SAS)',
                'tujuan_pembelajaran' => 'Mengevaluasi penguasaan kosa kata dan keterampilan reseptif-produktif bahasa Inggris semester 1.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Asesmen Akhir Semester 1',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Menyimak - Berbicara',
                'materi_pokok' => 'Daily Routines and Telling Time',
                'tujuan_pembelajaran' => 'Menyatakan jadwal kegiatan harian, waktu (telling time), dan frekuensi menggunakan simple present tense.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Membaca - Memirsa',
                'materi_pokok' => 'Procedure Text: Recipes and Manuals',
                'tujuan_pembelajaran' => 'Menemukan urutan langkah instruksi dan penggunaan kata penghubung (first, then, finally) pada resep makanan.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Menulis - Mempresentasikan',
                'materi_pokok' => 'Creating Culinary or DIY Procedure Posters',
                'tujuan_pembelajaran' => 'Merancang poster teks prosedur kreatif dan mempresentasikannya di depan kelas menggunakan bahasa Inggris sederhana.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 6',
            ],
            [
                'semester' => 2, 'urutan' => 4,
                'elemen_cp' => 'Semua Elemen',
                'materi_pokok' => 'Review & Asesmen Sumatif Akhir Tahun (SAT)',
                'tujuan_pembelajaran' => 'Mengevaluasi kompetensi komunikasi bahasa Inggris lisan dan tulis tingkat pemula lanjutan.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafPancasilaFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Pancasila',
                'materi_pokok' => 'Sejarah Kelahiran dan Penetapan Pancasila sebagai Dasar Negara',
                'tujuan_pembelajaran' => 'Menganalisis kronologi perumusan Pancasila oleh para pendiri bangsa dalam sidang BPUPKI dan PPKI serta meneladani semangat patriotisme.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Undang-Undang Dasar NRI 1945',
                'materi_pokok' => 'Norma Masyarakat dan UUD NRI Tahun 1945',
                'tujuan_pembelajaran' => 'Menerapkan norma kesopanan, kesusilaan, agama, dan hukum serta mematuhi tata tertib bermasyarakat dan sekolah.',
                'alokasi_waktu_jp' => 24, 'minggu_efektif' => 8,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Pancasila & UUD 1945',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Semester (SAS)',
                'tujuan_pembelajaran' => 'Mengevaluasi pemahaman konsep dasar kewarganegaraan semester 1.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 4,
                'keterangan' => 'Asesmen Akhir Semester 1',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Bhinneka Tunggal Ika',
                'materi_pokok' => 'Keragaman Budaya, Agama, Ras, dan Antargolongan dalam Bingkai Harmoni',
                'tujuan_pembelajaran' => 'Menghargai keberagaman suku dan kearifan lokal nusantara serta menolak diskriminasi dan perundungan.',
                'alokasi_waktu_jp' => 24, 'minggu_efektif' => 8,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Negara Kesatuan Republik Indonesia (NKRI)',
                'materi_pokok' => 'Wilayah NKRI, Semangat Persatuan, dan Bela Negara',
                'tujuan_pembelajaran' => 'Menunjukkan partisipasi aktif dalam menjaga keutuhan wilayah NKRI dan kebersihan lingkungan sekolah sebagai wujud bela negara.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'NKRI & Bhinneka Tunggal Ika',
                'materi_pokok' => 'Cadangan & Asesmen Sumatif Akhir Tahun (SAT)',
                'tujuan_pembelajaran' => 'Mengukur pencapaian profil pelajar Pancasila dan penguasaan materi tahunan.',
                'alokasi_waktu_jp' => 12, 'minggu_efektif' => 2,
                'keterangan' => 'Asesmen Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafInformatikaFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Berpikir Komputasional (BK)',
                'materi_pokok' => 'Dekomposisi, Pola, Abstraksi, dan Perancangan Algoritma',
                'tujuan_pembelajaran' => 'Menerapkan 4 pilar berpikir komputasional dalam menyelesaikan masalah logika dan teka-teki komputasi.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Teknologi Informasi dan Komunikasi (TIK)',
                'materi_pokok' => 'Pengelolaan Berkas, Surel, dan Aplikasi Pengolah Kata/Angka',
                'tujuan_pembelajaran' => 'Mengoperasikan pengolah kata dan lembar kerja untuk membuat laporan dokumen terstruktur serta menyimpan file di penyimpanan awan.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 7,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Sistem Komputer (SK)',
                'materi_pokok' => 'Perangkat Keras, Perangkat Lunak, dan Interaksi Sistem',
                'tujuan_pembelajaran' => 'Menjelaskan mekanisme kerja CPU, memori, input/output, serta sistem operasi komputer.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 3 / SAS',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Jaringan Komputer dan Internet (JKI)',
                'materi_pokok' => 'Koneksi Internet, Web, dan Keamanan Data Pribadi',
                'tujuan_pembelajaran' => 'Menjelaskan cara kerja transmisi data, peramban web, dan membiasakan proteksi password serta privasi digital.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Algoritma dan Pemrograman (AP)',
                'materi_pokok' => 'Pemrograman Visual Berbasis Blok (Scratch / Blockly)',
                'tujuan_pembelajaran' => 'Membuat program animasi atau gim interaktif sederhana menggunakan blok perintah perulangan, variabel, dan percabangan.',
                'alokasi_waktu_jp' => 22, 'minggu_efektif' => 7,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Dampak Sosial Informatika (DSI)',
                'materi_pokok' => 'Etika Berinternet (Netiket), Jejak Digital, dan Hak Kekayaan Intelektual',
                'tujuan_pembelajaran' => 'Mempraktikkan literasi digital yang bertanggung jawab serta menghargai lisensi karya orang lain.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafPaiFaseD(string $namaMapel, int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Al-Qur\'an dan Hadis',
                'materi_pokok' => 'Membaca dan Memahami Ayat-Ayat Perintah Menuntut Ilmu dan Ketaatan',
                'tujuan_pembelajaran' => 'Membaca tartil, menghafal, dan mengamalkan pesan ketaatan kepada Allah, Rasul, dan pemimpin serta keutamaan ilmu.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Akidah / Akhlak',
                'materi_pokok' => 'Keteladanan Asmaulhusna dan Perilaku Terpuji Sehari-hari',
                'tujuan_pembelajaran' => 'Meneladani sifat-sifat Allah dalam kehidupan nyata dengan menunjukkan kejujuran, amanah, dan sikap rendah hati.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 7,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Fikih Ibadah',
                'materi_pokok' => 'Ketentuan Salat Fardu, Salat Berjemaah, dan Sujud Sunah',
                'tujuan_pembelajaran' => 'Mempraktikkan tata cara salat yang sah, salat jumat, serta sujud syukur, sujud tilawah, dan sujud sahwi sesuai sunah.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 3 / SAS',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Akidah',
                'materi_pokok' => 'Iman kepada Malaikat-Malaikat Allah SWT',
                'tujuan_pembelajaran' => 'Mengimani eksistensi dan tugas-tugas malaikat serta menumbuhkan integritas pribadi yang senantiasa merasa diawasi Allah.',
                'alokasi_waktu_jp' => 16, 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Akhlak Mulia',
                'materi_pokok' => 'Menghindari Sikap Tercela (Gibah, Riya, dan Ujub) serta Membiasakan Tabayun',
                'tujuan_pembelajaran' => 'Menjaga lisan dan perbuatan dari tindakan merugikan sesama serta menerapkan etika bertutur kata santun di media sosial.',
                'alokasi_waktu_jp' => 20, 'minggu_efektif' => 7,
                'keterangan' => 'Sumatif Lingkup Materi 5',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Sejarah Peradaban Islam',
                'materi_pokok' => 'Keemasan Peradaban Islam dan Keteladanan Tokoh Muslim',
                'tujuan_pembelajaran' => 'Menganalisis kemajuan ilmu pengetahuan pada masa peradaban Islam klasik dan meneladani kegigihan ilmuwan muslim.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafPjokFaseD(int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Keterampilan Gerak & Pengetahuan Gerak',
                'materi_pokok' => 'Keterampilan Gerak Spesifik Permainan Bola Besar (Sepak Bola / Bola Voli / Bola Basket)',
                'tujuan_pembelajaran' => 'Mempraktikkan dan menganalisis teknik dasar mengoper, menggiring, dan menembak bola dengan koordinasi gerak yang baik.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Keterampilan Gerak & Pemanfaatan Gerak',
                'materi_pokok' => 'Gerak Spesifik Atletik (Lari Jarak Pendek, Lompat Jauh, Tolak Peluru)',
                'tujuan_pembelajaran' => 'Mempraktikkan teknik awalan, tolakan, dan pendaratan pada nomor atletik serta mengukur catatan performa fisik.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Pengembangan Karakter',
                'materi_pokok' => 'Kebugaran Jasmani dan Pola Hidup Sehat Remaja',
                'tujuan_pembelajaran' => 'Melakukan latihan kekuatan, kelenturan, dan daya tahan jantung serta merancang jadwal aktivitas fisik teratur.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Akhir Semester 1',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Keterampilan Gerak',
                'materi_pokok' => 'Keterampilan Gerak Spesifik Permainan Bola Kecil (Bulu Tangkis / Tenis Meja)',
                'tujuan_pembelajaran' => 'Mempraktikkan variasi pukulan servis, lob, dan smash dengan mengedepankan sportivitas.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Pengetahuan & Pemanfaatan Gerak',
                'materi_pokok' => 'Aktivitas Senam Lantai dan Gerak Berirama',
                'tujuan_pembelajaran' => 'Memperagakan rangkaian gerak berguling, keseimbangan, serta senam kebugaran dengan irama musik.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Pemanfaatan Gerak & Karakter',
                'materi_pokok' => 'Pencegahan Bahaya Pergaulan Bebas, Narkoba, dan Pertolongan Pertama (P3K)',
                'tujuan_pembelajaran' => 'Memahami bahaya zat adiktif serta mempraktikkan tindakan pertolongan pertama pada cedera ringan.',
                'alokasi_waktu_jp' => 18, 'minggu_efektif' => 4,
                'keterangan' => 'Sumatif Akhir Tahun',
            ],
        ];
    }

    /** @return list<ProtaRow> */
    protected function drafGenerik(string $namaMapel, string $fase, int $jp): array
    {
        return [
            // Semester 1
            [
                'semester' => 1, 'urutan' => 1,
                'elemen_cp' => 'Pemahaman Konsep dan Teori Dasar',
                'materi_pokok' => "Pengenalan dan Konsep Dasar {$namaMapel}",
                'tujuan_pembelajaran' => "Peserta didik mampu memahami konsep fundamental, ruang lingkup, dan prinsip dasar {$namaMapel} secara komprehensif.",
                'alokasi_waktu_jp' => max(1, $jp * 5), 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 1',
            ],
            [
                'semester' => 1, 'urutan' => 2,
                'elemen_cp' => 'Keterampilan Proses dan Analisis',
                'materi_pokok' => "Pendalaman Materi dan Studi Kasus {$namaMapel}",
                'tujuan_pembelajaran' => "Peserta didik mampu mengidentifikasi masalah, menganalisis data dan fakta terkait {$namaMapel} dalam kehidupan nyata.",
                'alokasi_waktu_jp' => max(1, $jp * 6), 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 2',
            ],
            [
                'semester' => 1, 'urutan' => 3,
                'elemen_cp' => 'Aplikasi dan Refleksi',
                'materi_pokok' => 'Penerapan Praktik dan Evaluasi Semester 1',
                'tujuan_pembelajaran' => 'Peserta didik mampu mendemonstrasikan pemahaman melalui presentasi/proyek karya dan evaluasi capaian semester 1.',
                'alokasi_waktu_jp' => max(1, $jp * 7), 'minggu_efektif' => 7,
                'keterangan' => 'Sumatif Akhir Semester (SAS)',
            ],

            // Semester 2
            [
                'semester' => 2, 'urutan' => 1,
                'elemen_cp' => 'Pengembangan Konsep Tingkat Lanjut',
                'materi_pokok' => "Konsep Lanjutan dan Kolaborasi Terapan {$namaMapel}",
                'tujuan_pembelajaran' => "Peserta didik mampu mengintegrasikan konsep lanjutan {$namaMapel} dengan konteks multidisiplin.",
                'alokasi_waktu_jp' => max(1, $jp * 5), 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Lingkup Materi 3',
            ],
            [
                'semester' => 2, 'urutan' => 2,
                'elemen_cp' => 'Kreasi dan Inovasi Solutif',
                'materi_pokok' => 'Pemecahan Masalah dan Proyek Mandiri/Kelompok',
                'tujuan_pembelajaran' => "Peserta didik mampu merumuskan solusi inovatif dan membuat karya/laporan ilmiah berdasarkan materi {$namaMapel}.",
                'alokasi_waktu_jp' => max(1, $jp * 6), 'minggu_efektif' => 6,
                'keterangan' => 'Sumatif Lingkup Materi 4',
            ],
            [
                'semester' => 2, 'urutan' => 3,
                'elemen_cp' => 'Evaluasi Akhir dan Refleksi Tahunan',
                'materi_pokok' => 'Review Materi Tahunan & Asesmen Sumatif Akhir Tahun',
                'tujuan_pembelajaran' => "Peserta didik mampu merefleksikan seluruh capaian pembelajaran {$namaMapel} selama satu tahun pelajaran.",
                'alokasi_waktu_jp' => max(1, $jp * 5), 'minggu_efektif' => 5,
                'keterangan' => 'Sumatif Akhir Tahun (SAT)',
            ],
        ];
    }
}
