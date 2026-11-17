<?php

namespace App\Services;

use App\Models\KalenderAkademik;
use App\Services\Gemini\KlienGemini;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiKurikulumService
{
    public function __construct(protected ?KlienGemini $klien = null)
    {
        $this->klien ??= app(KlienGemini::class);
    }

    /**
     * Membaca template prompt dari resources/prompts/
     */
    protected function bacaPrompt(string $namaFile): string
    {
        $path = resource_path('prompts/'.$namaFile);
        if (! file_exists($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    /**
     * Tahap 1: Generate & Sinkronkan AME, ATP, PROTA, PROSEM
     * Menggunakan tahunan_system.txt dan tahunan_user.txt
     */
    public function generateTahunan(array $params): array
    {
        set_time_limit(120);

        $systemPrompt = $this->bacaPrompt('tahunan_system.txt');
        $userPrompt = $this->bacaPrompt('tahunan_user.txt');

        $kalenderJson = '""';
        if (! empty($params['tahun_ajaran_id'])) {
            $agenda = KalenderAkademik::query()
                ->where('tahun_ajaran_id', (int) $params['tahun_ajaran_id'])
                ->orderBy('tanggal_mulai')
                ->get(['nama', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'keterangan'])
                ->map(fn (KalenderAkademik $k) => [
                    'nama' => $k->nama,
                    'jenis' => $k->jenis->value,
                    'tanggal_mulai' => $k->tanggal_mulai->format('Y-m-d'),
                    'tanggal_selesai' => $k->tanggal_selesai->format('Y-m-d'),
                    'keterangan' => $k->keterangan,
                ])
                ->all();

            if (! empty($agenda)) {
                $kalenderJson = json_encode($agenda, JSON_UNESCAPED_UNICODE) ?: '""';
            }
        }

        // Ganti variabel placeholder {{...}}
        $placeholders = [
            '{{nama_madrasah}}' => $params['satuan_pendidikan'] ?? 'MTs At-Taufiq Cisurupan',
            '{{nama_kepala}}' => $params['kepala_madrasah'] ?? '',
            '{{nip_kepala}}' => $params['nip_kepala'] ?? '-',
            '{{nama_guru}}' => $params['guru_pengampu'] ?? 'Guru Pengampu',
            '{{nip_guru}}' => $params['nip_guru'] ?? '-',
            '{{kota}}' => $params['kota_titimangsa'] ?? 'Cisurupan',
            '{{tanggal}}' => $params['tanggal_titimangsa'] ?? now()->format('Y-m-d'),
            '{{mapel}}' => $params['mata_pelajaran'] ?? 'Matematika',
            '{{kelas}}' => $params['kelas'] ?? 'VII',
            '{{fase}}' => $params['fase'] ?? 'D',
            '{{tahun_pelajaran}}' => $params['tahun_pelajaran'] ?? '2026/2027',
            '{{jp_per_minggu}}' => (string) ($params['jp_per_minggu'] ?? 5),
            '{{json_kalender_atau_kosong_untuk_otomatis}}' => $kalenderJson,
        ];

        $renderedUserPrompt = str_replace(array_keys($placeholders), array_values($placeholders), $userPrompt);

        $hasil = null;

        if (KlienGemini::aktif()) {
            try {
                $rawJson = $this->klien->hasilkan(
                    $systemPrompt,
                    $renderedUserPrompt,
                    ['responseMimeType' => 'application/json', 'maxOutputTokens' => 8192],
                    120
                );

                $decoded = json_decode($rawJson, true);
                if (is_array($decoded) && isset($decoded['master_tp'])) {
                    $hasil = $decoded;
                }
            } catch (Throwable $e) {
                Log::warning('GeminiKurikulumService: Gagal generate dari Gemini: '.$e->getMessage());
            }
        }

        // Jika Gemini belum aktif atau gagal, gunakan draf standar kurikulum merdeka
        if (! $hasil) {
            $hasil = $this->drafStandarTahunan(
                $params['mata_pelajaran'] ?? 'Matematika',
                $params['kelas'] ?? 'VII',
                $params['fase'] ?? 'D',
                (int) ($params['jp_per_minggu'] ?? 5)
            );
        }

        // Validasi matematika JP & sinkronisasi mutlak di sisi PHP
        return $this->validasiDanSinkronkanTahunan($hasil, (int) ($params['jp_per_minggu'] ?? 5), $params);
    }

    /**
     * Tahap 2: Generate Modul Ajar (on-demand per TP)
     * Menggunakan modul_system.txt dan modul_user.txt
     */
    public function generateModulAjar(array $identitasSekolah, array $tpTerpilih, int $jpPerMinggu = 5): array
    {
        set_time_limit(120);

        $systemPrompt = $this->bacaPrompt('modul_system.txt');
        $userPrompt = $this->bacaPrompt('modul_user.txt');

        $placeholders = [
            '{{json_identitas_sekolah}}' => json_encode($identitasSekolah, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{json_satu_item_master_tp}}' => json_encode($tpTerpilih, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{jp_per_minggu}}' => (string) $jpPerMinggu,
        ];

        $renderedUserPrompt = str_replace(array_keys($placeholders), array_values($placeholders), $userPrompt);

        if (KlienGemini::aktif()) {
            try {
                $rawJson = $this->klien->hasilkan(
                    $systemPrompt,
                    $renderedUserPrompt,
                    ['responseMimeType' => 'application/json', 'maxOutputTokens' => 8192],
                    120
                );

                $decoded = json_decode($rawJson, true);
                if (is_array($decoded) && isset($decoded['identitas'])) {
                    return $decoded;
                }
            } catch (Throwable $e) {
                Log::warning('GeminiKurikulumService: Gagal generate modul dari Gemini: '.$e->getMessage());
            }
        }

        // Fallback draf modul ajar lengkap 18 komponen jika Gemini tidak aktif
        return $this->drafStandarModulAjar($identitasSekolah, $tpTerpilih, $jpPerMinggu);
    }

    /**
     * Memvalidasi dan menyeimbangkan matematika JP kalender dan master_tp
     */
    public function validasiDanSinkronkanTahunan(array $data, int $jpPerMinggu, array $params = []): array
    {
        $ame = $data['analisis_minggu_efektif'] ?? [];
        $sem1 = ! empty($ame['semester_1']) ? $ame['semester_1'] : $this->drafKalenderSemester1();
        $sem2 = ! empty($ame['semester_2']) ? $ame['semester_2'] : $this->drafKalenderSemester2();

        // Hitung ulang minggu efektif tiap bulan & pastikan jumlah indeks_minggu_efektif == mgg_efektif
        $sem1 = $this->normalisasiBulanAme($sem1);
        $sem2 = $this->normalisasiBulanAme($sem2);

        $totalMggSem1 = (int) collect($sem1)->sum('mgg_efektif');
        $totalMggSem2 = (int) collect($sem2)->sum('mgg_efektif');

        $totalJpSem1 = $totalMggSem1 * $jpPerMinggu;
        $totalJpSem2 = $totalMggSem2 * $jpPerMinggu;
        $totalJpSetahun = $totalJpSem1 + $totalJpSem2;

        $data['analisis_minggu_efektif'] = [
            'semester_1' => $sem1,
            'semester_2' => $sem2,
            'ringkasan' => [
                'total_mgg_efektif_sem_1' => $totalMggSem1,
                'total_mgg_efektif_sem_2' => $totalMggSem2,
                'total_jp_sem_1' => $totalJpSem1,
                'total_jp_sem_2' => $totalJpSem2,
                'total_jp_setahun' => $totalJpSetahun,
            ],
        ];

        // Validasi & seimbangkan master_tp (pastikan Semester 1 dan Semester 2 keduanya terisi)
        $masterTp = $data['master_tp'] ?? [];
        $tpSem1 = array_values(array_filter($masterTp, fn ($t) => (int) ($t['semester'] ?? 1) === 1));
        $tpSem2 = array_values(array_filter($masterTp, fn ($t) => (int) ($t['semester'] ?? 1) === 2));

        if (empty($tpSem1) || empty($tpSem2)) {
            $fallback = $this->drafStandarTahunan(
                $params['mata_pelajaran'] ?? 'Mata Pelajaran',
                $params['kelas'] ?? 'VII',
                $params['fase'] ?? 'D',
                $jpPerMinggu
            );
            $fallbackTp = $fallback['master_tp'] ?? [];

            if (empty($tpSem1)) {
                $tpSem1 = array_values(array_filter($fallbackTp, fn ($t) => (int) ($t['semester'] ?? 1) === 1));
            }
            if (empty($tpSem2)) {
                $tpSem2 = array_values(array_filter($fallbackTp, fn ($t) => (int) ($t['semester'] ?? 1) === 2));
            }
        }

        $this->seimbangkanJpSemester($tpSem1, $totalJpSem1, $jpPerMinggu, 'semester_1', $sem1);
        $this->seimbangkanJpSemester($tpSem2, $totalJpSem2, $jpPerMinggu, 'semester_2', $sem2);

        $data['master_tp'] = array_merge($tpSem1, $tpSem2);

        return $data;
    }

    /**
     * Normalisasi data AME tiap bulan agar mgg_efektif dan jumlah indeks_minggu_efektif selalu sinkron.
     */
    protected function normalisasiBulanAme(array $bulanList): array
    {
        foreach ($bulanList as &$bulan) {
            $jml = max(1, (int) ($bulan['jml_minggu'] ?? 4));
            $tdk = max(0, min($jml, (int) ($bulan['mgg_tidak_efektif'] ?? 0)));
            $efk = max(0, $jml - $tdk);

            $bulan['jml_minggu'] = $jml;
            $bulan['mgg_tidak_efektif'] = $tdk;
            $bulan['mgg_efektif'] = $efk;

            $indeks = is_array($bulan['indeks_minggu_efektif'] ?? null)
                ? array_values(array_unique(array_filter(
                    array_map('intval', $bulan['indeks_minggu_efektif']),
                    fn (int $i): bool => $i >= 0 && $i < $jml
                )))
                : [];

            if (count($indeks) > $efk) {
                $indeks = array_slice($indeks, 0, $efk);
            } elseif (count($indeks) < $efk) {
                for ($i = 0; $i < $jml && count($indeks) < $efk; $i++) {
                    if (! in_array($i, $indeks, true)) {
                        $indeks[] = $i;
                    }
                }
            }

            sort($indeks);
            $bulan['indeks_minggu_efektif'] = $indeks;
        }
        unset($bulan);

        return $bulanList;
    }

    /**
     * Menyeimbangkan alokasi JP TP dalam 1 semester dan menyesuaikan matriks jadwal_prosem
     * hanya pada minggu yang berstatus efektif di AME.
     */
    protected function seimbangkanJpSemester(array &$tpList, int $targetJp, int $jpPerMinggu, string $semesterKey, array $ameBulan = []): void
    {
        if (empty($tpList)) {
            return;
        }

        $sumJp = array_sum(array_column($tpList, 'alokasi_jp'));
        $selisih = $targetJp - $sumJp;

        if ($selisih !== 0 && count($tpList) > 0) {
            $lastIndex = count($tpList) - 1;
            $tpList[$lastIndex]['alokasi_jp'] = max($jpPerMinggu, (int) $tpList[$lastIndex]['alokasi_jp'] + $selisih);
        }

        $bulanNames = $semesterKey === 'semester_1'
            ? ['juli', 'agustus', 'september', 'oktober', 'november', 'desember']
            : ['januari', 'februari', 'maret', 'april', 'mei', 'juni'];

        // Petakan data AME per nama bulan (lowercase)
        $ameMap = [];
        foreach ($ameBulan as $row) {
            $nama = mb_strtolower((string) ($row['bulan'] ?? ''));
            if ($nama !== '') {
                $ameMap[$nama] = $row;
            }
        }

        // Kumpulkan slot minggu efektif secara berurutan: [bulan, indexMinggu]
        $slotEfektif = [];
        $templateBulan = [];
        foreach ($bulanNames as $bulan) {
            $info = $ameMap[$bulan] ?? null;
            $jmlMgg = $info ? (int) ($info['jml_minggu'] ?? 4) : (($bulan === 'juli' || $bulan === 'september' || $bulan === 'desember' || $bulan === 'maret' || $bulan === 'mei') ? 5 : 4);
            $templateBulan[$bulan] = array_fill(0, $jmlMgg, 0);

            $indeksEfektif = $info['indeks_minggu_efektif'] ?? null;
            if (! is_array($indeksEfektif)) {
                $mggEfektif = $info ? (int) ($info['mgg_efektif'] ?? $jmlMgg) : $jmlMgg;
                $indeksEfektif = $mggEfektif > 0 ? range(0, min($jmlMgg, $mggEfektif) - 1) : [];
            }

            foreach ($indeksEfektif as $idxMgg) {
                $idxInt = (int) $idxMgg;
                if ($idxInt >= 0 && $idxInt < $jmlMgg) {
                    $slotEfektif[] = [$bulan, $idxInt];
                }
            }
        }

        $slotCursor = 0;
        $totalSlots = count($slotEfektif);

        foreach ($tpList as &$tp) {
            $alokasiJp = (int) ($tp['alokasi_jp'] ?? $jpPerMinggu);
            $tp['jumlah_minggu'] = max(1, (int) ceil($alokasiJp / max(1, $jpPerMinggu)));

            $jadwal = $templateBulan;
            $sisaJpToDistribute = $alokasiJp;

            while ($sisaJpToDistribute > 0 && $slotCursor < $totalSlots) {
                [$bulan, $w] = $slotEfektif[$slotCursor];
                $porsi = min($jpPerMinggu, $sisaJpToDistribute);
                $jadwal[$bulan][$w] = $porsi;
                $sisaJpToDistribute -= $porsi;
                $slotCursor++;
            }

            $tp['jadwal_prosem'] = $jadwal;
        }
        unset($tp);
    }

    /**
     * Draf Kalender Standar Semester 1 (Juli-Desember)
     */
    protected function drafKalenderSemester1(): array
    {
        return [
            ['bulan' => 'Juli', 'jml_minggu' => 5, 'mgg_tidak_efektif' => 3, 'mgg_efektif' => 2, 'indeks_minggu_efektif' => [3, 4], 'keterangan' => 'M1-M2 Libur Akhir TP, M3 MATSAMA'],
            ['bulan' => 'Agustus', 'jml_minggu' => 4, 'mgg_tidak_efektif' => 0, 'mgg_efektif' => 4, 'indeks_minggu_efektif' => [0, 1, 2, 3], 'keterangan' => 'KBM Efektif, HUT RI'],
            ['bulan' => 'September', 'jml_minggu' => 5, 'mgg_tidak_efektif' => 1, 'mgg_efektif' => 4, 'indeks_minggu_efektif' => [0, 1, 2, 3], 'keterangan' => 'M5 Sumatif Tengah Semester (STS)'],
            ['bulan' => 'Oktober', 'jml_minggu' => 4, 'mgg_tidak_efektif' => 0, 'mgg_efektif' => 4, 'indeks_minggu_efektif' => [0, 1, 2, 3], 'keterangan' => 'KBM Efektif'],
            ['bulan' => 'November', 'jml_minggu' => 4, 'mgg_tidak_efektif' => 0, 'mgg_efektif' => 4, 'indeks_minggu_efektif' => [0, 1, 2, 3], 'keterangan' => 'KBM Efektif'],
            ['bulan' => 'Desember', 'jml_minggu' => 5, 'mgg_tidak_efektif' => 5, 'mgg_efektif' => 0, 'indeks_minggu_efektif' => [], 'keterangan' => 'M1-M2 SAS, M3 Pengolahan Nilai, M4 Pembagian Rapor, M5 Libur Semester 1'],
        ];
    }

    /**
     * Draf Kalender Standar Semester 2 (Januari-Juni 2027)
     */
    protected function drafKalenderSemester2(): array
    {
        return [
            ['bulan' => 'Januari', 'jml_minggu' => 5, 'mgg_tidak_efektif' => 1, 'mgg_efektif' => 4, 'indeks_minggu_efektif' => [1, 2, 3, 4], 'keterangan' => 'M1 Libur Semester 1, HAB Kemenag'],
            ['bulan' => 'Februari', 'jml_minggu' => 4, 'mgg_tidak_efektif' => 1, 'mgg_efektif' => 3, 'indeks_minggu_efektif' => [0, 1, 3], 'keterangan' => 'M3 Libur Awal Ramadhan 1448 H, Isra Miraj, KBM Ramadhan'],
            ['bulan' => 'Maret', 'jml_minggu' => 5, 'mgg_tidak_efektif' => 2, 'mgg_efektif' => 3, 'indeks_minggu_efektif' => [0, 1, 4], 'keterangan' => 'M3-M4 Libur Hari Raya Idul Fitri 1448 H'],
            ['bulan' => 'April', 'jml_minggu' => 4, 'mgg_tidak_efektif' => 1, 'mgg_efektif' => 3, 'indeks_minggu_efektif' => [1, 2, 3], 'keterangan' => 'M1 Sumatif Tengah Semester (STS), KBM Efektif'],
            ['bulan' => 'Mei', 'jml_minggu' => 5, 'mgg_tidak_efektif' => 1, 'mgg_efektif' => 4, 'indeks_minggu_efektif' => [0, 1, 2, 3], 'keterangan' => 'M5 Asesmen Madrasah / Persiapan SAT'],
            ['bulan' => 'Juni', 'jml_minggu' => 4, 'mgg_tidak_efektif' => 4, 'mgg_efektif' => 0, 'indeks_minggu_efektif' => [], 'keterangan' => 'M1 SAT/SAS, M2 Pengolahan Nilai, M3 Pembagian Rapor, M4 Libur Akhir TP'],
        ];
    }

    /**
     * Draf Standar Kurikulum Merdeka Fase D (MTs / SMP)
     */
    public function drafStandarTahunan(string $mapel, string $kelas, string $fase, int $jpPerMinggu): array
    {
        $sem1 = $this->drafKalenderSemester1();
        $sem2 = $this->drafKalenderSemester2();

        $totalMgg1 = (int) collect($sem1)->sum('mgg_efektif'); // 18 mgg
        $totalMgg2 = (int) collect($sem2)->sum('mgg_efektif'); // 17 mgg

        $mapelLower = mb_strtolower($mapel);

        if (str_contains($mapelLower, 'matematik')) {
            $tps = [
                // Semester 1
                [
                    'semester' => 1,
                    'kode_tp' => '7.1',
                    'elemen_cp' => 'Bilangan',
                    'capaian_pembelajaran' => 'Peserta didik dapat membaca, menulis, dan membandingkan bilangan bulat, bilangan rasional dan irasional, bilangan desimal, bilangan berpangkat bulat dan akar, bilangan dalam notasi ilmiah.',
                    'materi_pokok' => 'Bilangan Bulat dan Pecahan: Operasi Hitung dan Penerapannya',
                    'tujuan_pembelajaran' => 'Peserta didik mampu memahami konsep bilangan bulat dan pecahan, melakukan operasi hitung aritmetika dasar, serta menyelesaikan permasalahan kontekstual kehidupan sehari-hari.',
                    'alokasi_jp' => 25,
                    'jumlah_minggu' => 5,
                    'jenis_asesmen' => 'Formatif (Tugas/LKPD) & Sumatif LM 1',
                ],
                [
                    'semester' => 1,
                    'kode_tp' => '7.2',
                    'elemen_cp' => 'Aljabar',
                    'capaian_pembelajaran' => 'Peserta didik dapat mengenali, memprediksi dan menggeneralisasi pola dalam bentuk susunan benda dan bilangan. Mereka dapat menyatakan suatu situasi ke dalam bentuk aljabar.',
                    'materi_pokok' => 'Bentuk Aljabar: Variabel, Konstanta, Koefisien, dan Operasi Penjumlahan/Pengurangan',
                    'tujuan_pembelajaran' => 'Peserta didik mampu memodelkan situasi nyata ke dalam bentuk aljabar serta melakukan operasi aljabar sederhana secara terampil dan teliti.',
                    'alokasi_jp' => 30,
                    'jumlah_minggu' => 6,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 2',
                ],
                [
                    'semester' => 1,
                    'kode_tp' => '7.3',
                    'elemen_cp' => 'Aljabar',
                    'capaian_pembelajaran' => 'Peserta didik dapat menyelesaikan persamaan dan pertidaksamaan linier satu variabel.',
                    'materi_pokok' => 'Persamaan dan Pertidaksamaan Linear Satu Variabel (PLSV & PtLSV)',
                    'tujuan_pembelajaran' => 'Peserta didik mampu menemukan konsep kesetaraan, menyelesaikan PLSV dan PtLSV, serta menerapkannya dalam pemecahan masalah nyata.',
                    'alokasi_jp' => 35,
                    'jumlah_minggu' => 7,
                    'jenis_asesmen' => 'Formatif & Sumatif Akhir Semester (SAS)',
                ],

                // Semester 2
                [
                    'semester' => 2,
                    'kode_tp' => '7.4',
                    'elemen_cp' => 'Pengukuran & Geometri',
                    'capaian_pembelajaran' => 'Peserta didik dapat menggunakan hubungan antarsudut yang terbentuk oleh dua garis yang berpotongan, dan oleh dua garis sejajar yang dipotong sebuah garis transversal.',
                    'materi_pokok' => 'Garis dan Sudut: Hubungan Antarsudut dan Pembuktian Sederhana',
                    'tujuan_pembelajaran' => 'Peserta didik mampu mengidentifikasi sifat-sifat garis sejajar, menentukan besar sudut berpelurus, berpenyiku, dan sehadap dengan akurat.',
                    'alokasi_jp' => 25,
                    'jumlah_minggu' => 5,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 3',
                ],
                [
                    'semester' => 2,
                    'kode_tp' => '7.5',
                    'elemen_cp' => 'Geometri',
                    'capaian_pembelajaran' => 'Peserta didik dapat menjelaskan cara untuk menentukan luas lingkaran dan menyelesaikan masalah yang terkait.',
                    'materi_pokok' => 'Bangun Datar: Segiempat, Segitiga, dan Keliling-Luas',
                    'tujuan_pembelajaran' => 'Peserta didik mampu merumuskan keliling dan luas bangun datar segiempat dan segitiga dalam konteks pemecahan masalah lingkungan sekolah.',
                    'alokasi_jp' => 30,
                    'jumlah_minggu' => 6,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 4',
                ],
                [
                    'semester' => 2,
                    'kode_tp' => '7.6',
                    'elemen_cp' => 'Analisis Data dan Peluang',
                    'capaian_pembelajaran' => 'Peserta didik dapat merumuskan pertanyaan, mengumpulkan, menyajikan, dan menganalisis data untuk menjawab pertanyaan.',
                    'materi_pokok' => 'Penyajian dan Analisis Data: Tabel, Diagram Batang, Garis, dan Lingkaran',
                    'tujuan_pembelajaran' => 'Peserta didik mampu menyajikan data madrasah ke dalam diagram batang, garis, dan lingkaran serta menginterpretasikan informasinya secara kritis.',
                    'alokasi_jp' => 30,
                    'jumlah_minggu' => 6,
                    'jenis_asesmen' => 'Formatif & Sumatif Akhir Tahun (SAT)',
                ],
            ];
        } else {
            // General draf Kurikulum Merdeka (8 Bab / TP: 4 Semester 1 + 4 Semester 2)
            $jpSem1 = $totalMgg1 * $jpPerMinggu;
            $jpSem2 = $totalMgg2 * $jpPerMinggu;

            $mgg1A = max(1, (int) floor($totalMgg1 / 4));
            $mgg1B = max(1, (int) floor($totalMgg1 / 4));
            $mgg1C = max(1, (int) floor($totalMgg1 / 4));
            $mgg1D = max(1, $totalMgg1 - ($mgg1A + $mgg1B + $mgg1C));

            $mgg2A = max(1, (int) floor($totalMgg2 / 4));
            $mgg2B = max(1, (int) floor($totalMgg2 / 4));
            $mgg2C = max(1, (int) floor($totalMgg2 / 4));
            $mgg2D = max(1, $totalMgg2 - ($mgg2A + $mgg2B + $mgg2C));

            $tps = [
                // Semester 1 (4 Bab)
                [
                    'semester' => 1,
                    'kode_tp' => '1.1',
                    'elemen_cp' => 'Pemahaman Konsep Dasar',
                    'capaian_pembelajaran' => "Peserta didik memahami konsep fundamental dan ruang lingkup {$mapel} pada fase {$fase}.",
                    'materi_pokok' => "Bab 1: Pengenalan dan Konsep Dasar {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu mengidentifikasi prinsip-prinsip dasar {$mapel} serta menjelaskannya secara runtut.",
                    'alokasi_jp' => $mgg1A * $jpPerMinggu,
                    'jumlah_minggu' => $mgg1A,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 1',
                ],
                [
                    'semester' => 1,
                    'kode_tp' => '1.2',
                    'elemen_cp' => 'Analisis & Pemahaman Teks/Konteks',
                    'capaian_pembelajaran' => "Peserta didik mampu menelaah struktur, kaidah, dan hubungan antar konsep dalam {$mapel}.",
                    'materi_pokok' => "Bab 2: Struktur, Kaidah, dan Pendalaman {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu menganalisis unsur-unsur penting materi {$mapel} melalui studi kasus kontekstual.",
                    'alokasi_jp' => $mgg1B * $jpPerMinggu,
                    'jumlah_minggu' => $mgg1B,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 2',
                ],
                [
                    'semester' => 1,
                    'kode_tp' => '1.3',
                    'elemen_cp' => 'Keterampilan Proses & Komunikasi',
                    'capaian_pembelajaran' => "Peserta didik mampu menyajikan gagasan dan temuan terkait {$mapel} secara lisan maupun tertulis.",
                    'materi_pokok' => "Bab 3: Komunikasi Ilmiah dan Praktik {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu mengomunikasikan hasil telaah {$mapel} dengan bahasa yang santun, logis, dan sistematis.",
                    'alokasi_jp' => $mgg1C * $jpPerMinggu,
                    'jumlah_minggu' => $mgg1C,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 3',
                ],
                [
                    'semester' => 1,
                    'kode_tp' => '1.4',
                    'elemen_cp' => 'Aplikasi Kontekstual',
                    'capaian_pembelajaran' => "Peserta didik mampu menerapkan keterampilan {$mapel} dalam pemecahan masalah kehidupan sehari-hari.",
                    'materi_pokok' => "Bab 4: Penerapan Praktis {$mapel} di Lingkungan Madrasah",
                    'tujuan_pembelajaran' => "Peserta didik mampu merancang solusi dan mempresentasikan karya sederhana terkait materi {$mapel}.",
                    'alokasi_jp' => max($jpPerMinggu, $jpSem1 - (($mgg1A + $mgg1B + $mgg1C) * $jpPerMinggu)),
                    'jumlah_minggu' => $mgg1D,
                    'jenis_asesmen' => 'Formatif & Sumatif Akhir Semester (SAS)',
                ],
                // Semester 2 (4 Bab)
                [
                    'semester' => 2,
                    'kode_tp' => '2.1',
                    'elemen_cp' => 'Eksplorasi Lanjutan',
                    'capaian_pembelajaran' => "Peserta didik mampu mengeksplorasi konsep lanjutan {$mapel} serta kaitannya dengan kearifan lokal.",
                    'materi_pokok' => "Bab 5: Eksplorasi Materi Lanjutan {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu menganalisis hubungan sebab-akibat dan konteks budaya dalam pembelajaran {$mapel}.",
                    'alokasi_jp' => $mgg2A * $jpPerMinggu,
                    'jumlah_minggu' => $mgg2A,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 4',
                ],
                [
                    'semester' => 2,
                    'kode_tp' => '2.2',
                    'elemen_cp' => 'Integrasi Nilai & Literasi',
                    'capaian_pembelajaran' => "Peserta didik mampu mengintegrasikan pemahaman {$mapel} dengan nilai-nilai keislaman dan kebangsaan.",
                    'materi_pokok' => "Bab 6: Integrasi Nilai P5RA dalam {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu mengevaluasi pesan moral, nilai karakter, dan relevansi materi {$mapel} secara kritis.",
                    'alokasi_jp' => $mgg2B * $jpPerMinggu,
                    'jumlah_minggu' => $mgg2B,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 5',
                ],
                [
                    'semester' => 2,
                    'kode_tp' => '2.3',
                    'elemen_cp' => 'Produksi & Kreasi Karya',
                    'capaian_pembelajaran' => "Peserta didik mampu berkreasi menghasilkan produk/karya tulis atau praktik berbasis {$mapel}.",
                    'materi_pokok' => "Bab 7: Proyek Kreatif dan Karya Terapan {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu menyusun dan menyajikan karya inovatif {$mapel} secara mandiri maupun berkelompok.",
                    'alokasi_jp' => $mgg2C * $jpPerMinggu,
                    'jumlah_minggu' => $mgg2C,
                    'jenis_asesmen' => 'Formatif & Sumatif LM 6',
                ],
                [
                    'semester' => 2,
                    'kode_tp' => '2.4',
                    'elemen_cp' => 'Evaluasi & Refleksi Kritis',
                    'capaian_pembelajaran' => "Peserta didik mampu merefleksikan dan mengevaluasi penguasaan materi {$mapel} selama satu tahun pelajaran.",
                    'materi_pokok' => "Bab 8: Penguatan Kompetensi dan Refleksi Akhir {$mapel}",
                    'tujuan_pembelajaran' => "Peserta didik mampu menyelesaikan masalah terpadu {$mapel} dan merefleksikan capaian belajarnya.",
                    'alokasi_jp' => max($jpPerMinggu, $jpSem2 - (($mgg2A + $mgg2B + $mgg2C) * $jpPerMinggu)),
                    'jumlah_minggu' => $mgg2D,
                    'jenis_asesmen' => 'Formatif & Sumatif Akhir Tahun (SAT)',
                ],
            ];
        }

        return [
            'analisis_minggu_efektif' => [
                'semester_1' => $sem1,
                'semester_2' => $sem2,
                'ringkasan' => [
                    'total_mgg_efektif_sem_1' => $totalMgg1,
                    'total_mgg_efektif_sem_2' => $totalMgg2,
                    'total_jp_sem_1' => $totalMgg1 * $jpPerMinggu,
                    'total_jp_sem_2' => $totalMgg2 * $jpPerMinggu,
                    'total_jp_setahun' => ($totalMgg1 + $totalMgg2) * $jpPerMinggu,
                ],
            ],
            'master_tp' => $tps,
        ];
    }

    /**
     * Draf Standar Modul Ajar 18 Komponen Lengkap + LKPD + Rubrik
     */
    public function drafStandarModulAjar(array $identitasSekolah, array $tp, int $jpPerMinggu = 5): array
    {
        $alokasiJp = (int) ($tp['alokasi_jp'] ?? $jpPerMinggu);
        $jmlPertemuan = max(1, (int) ceil($alokasiJp / max(1, $jpPerMinggu)));

        $kegiatan = [];
        for ($p = 1; $p <= $jmlPertemuan; $p++) {
            $jpPertemuan = ($p === $jmlPertemuan && ($alokasiJp % $jpPerMinggu !== 0))
                ? ($alokasiJp % $jpPerMinggu)
                : $jpPerMinggu;

            $kegiatan[] = [
                'pertemuan_ke' => $p,
                'jp_pertemuan' => $jpPertemuan,
                'pendahuluan' => [
                    'Guru membuka pembelajaran dengan salam, menyapa peserta didik, dan mengajak berdoa bersama.',
                    'Guru memeriksa kehadiran, kesiapan fisik dan psikis peserta didik di kelas.',
                    'Guru melakukan apersepsi dengan mengaitkan materi sebelumnya dengan topik hari ini.',
                    'Guru menyampaikan tujuan pembelajaran, skenario aktivitas, dan manfaat materi bagi kehidupan.',
                ],
                'inti' => [
                    'Stimulus/Orientasi: Guru menampilkan media stimulus (gambar/video/studi kasus kontekstual).',
                    'Identifikasi Masalah: Peserta didik secara kritis merumuskan pertanyaan atas stimulus yang diamati.',
                    'Pengumpulan Data: Peserta didik berdiskusi dalam kelompok kerja mengerjakan LKPD terbimbing.',
                    'Pengolahan & Pembuktian: Peserta didik mengolah hasil telaah dan memverifikasi temuannya.',
                    'Menarik Kesimpulan & Presentasi: Perwakilan kelompok mempresentasikan hasil diskusi di depan kelas, kelompok lain menanggapi.',
                ],
                'penutup' => [
                    'Peserta didik bersama guru menyimpulkan poin-poin esensial materi pembelajaran.',
                    'Guru melaksanakan asesmen formatif kilat (exit ticket / refleksi singkat).',
                    'Guru memberikan umpan balik apresiatif dan menyampaikan rencana materi pertemuan berikutnya.',
                    'Pembelajaran diakhiri dengan doa penutup majelis dan salam.',
                ],
            ];
        }

        return [
            'identitas' => [
                'satuan_pendidikan' => $identitasSekolah['satuan_pendidikan'] ?? 'MTs At-Taufiq Cisurupan',
                'mata_pelajaran' => $identitasSekolah['mata_pelajaran'] ?? 'Matematika',
                'kelas_fase' => ($identitasSekolah['kelas'] ?? 'VII').' / Fase '.($identitasSekolah['fase'] ?? 'D'),
                'semester_tahun' => 'Semester '.($tp['semester'] ?? 1).' / '.($identitasSekolah['tahun_pelajaran'] ?? '2024/2025'),
                'kode_tp' => $tp['kode_tp'] ?? '1.1',
                'materi_pokok' => $tp['materi_pokok'] ?? 'Materi Pembelajaran Pokok',
                'alokasi_waktu_teks' => "{$jmlPertemuan} Pertemuan ({$alokasiJp} JP × 40 menit)",
                'penyusun' => $identitasSekolah['guru_pengampu'] ?? 'Guru Pengampu',
            ],
            'capaian_pembelajaran' => $tp['capaian_pembelajaran'] ?? 'Capaian Pembelajaran sesuai fase dan elemen kurikulum.',
            'tujuan_pembelajaran' => [
                $tp['tujuan_pembelajaran'] ?? 'Peserta didik memahami konsep pokok dan memecahkan masalah terkait materi.',
            ],
            'kompetensi_awal' => [
                'Peserta didik telah memiliki pengetahuan dasar prasyarat yang relevan dengan topik ini.',
                'Peserta didik memiliki kemampuan dasar literasi dan numerasi untuk menganalisis data sederhana.',
            ],
            'profil_p5ra' => [
                [
                    'dimensi' => 'Beriman, Bertakwa kepada Tuhan YME, dan Berakhlak Mulia',
                    'deskripsi' => 'Membiasakan doa sebelum dan sesudah belajar serta bersikap santun dan jujur dalam berpendapat.',
                ],
                [
                    'dimensi' => 'Bernalar Kritis',
                    'deskripsi' => 'Mampu memproses informasi, mengidentifikasi argumen logis, dan menyelesaikan persoalan bertahap.',
                ],
                [
                    'dimensi' => 'Gotong Royong',
                    'deskripsi' => 'Mampu bekerja sama, berbagi peran secara adil dalam kelompok, dan saling menghargai gagasan.',
                ],
            ],
            'sarana_prasarana' => [
                'media' => 'Papan tulis, LCD Proyektor, tayangan salindia presentasi, dan LKPD cetak.',
                'alat' => 'Spidol, penggaris, buku catatan, dan alat tulis peserta didik.',
                'sumber_belajar' => 'Buku Siswa Kemendikbudristek/Kemenag, modul ajar guru, dan sumber belajar lingkungan sekitar madrasah.',
            ],
            'model_metode' => [
                'model' => 'Problem Based Learning (PBL) / Discovery Learning',
                'pendekatan' => 'Saintifik & Deep Learning Berbasis Madrasah',
                'metode' => 'Diskusi kelompok, tanya jawab interaktif, demonstrasi, dan penugasan mandiri',
            ],
            'pemahaman_bermakna' => 'Peserta didik menyadari bahwa materi yang dipelajari memberikan keterampilan esensial dalam menyelesaikan problematika kehidupan nyata secara logis dan terstruktur.',
            'pertanyaan_pemantik' => [
                'Mengapa topik materi ini sangat penting kita pelajari dalam kehidupan sehari-hari?',
                'Bagaimana konsep ini dapat membantu kita mempermudah pekerjaan atau menyelesaikan masalah nyata?',
            ],
            'kegiatan_pembelajaran' => $kegiatan,
            'asesmen' => [
                'diagnostik' => [
                    'Tanya jawab pemantik di awal pembelajaran untuk mengecek kesiapan belajar.',
                    'Kuis singkat 3 soal untuk memetakan pemahaman prasyarat peserta didik.',
                ],
                'formatif_rubrik' => [
                    [
                        'aspek' => 'Pemahaman Konsep Materi',
                        'sangat_baik' => 'Menjelaskan seluruh konsep secara akurat, mendalam, dan mampu memberi contoh relevan.',
                        'baik' => 'Menjelaskan konsep utama dengan tepat dan sebagian besar contoh benar.',
                        'cukup' => 'Menjelaskan sebagian konsep dasar namun masih membutuhkan penguatan konsep lanjutan.',
                        'perlu_bimbingan' => 'Belum mampu menjelaskan konsep dasar tanpa pendampingan penuh dari guru.',
                    ],
                    [
                        'aspek' => 'Keterampilan Penyelesaian Masalah',
                        'sangat_baik' => 'Langkah penyelesaian sistematis, terstruktur, perhitungan/analisis tepat tanpa kesalahan.',
                        'baik' => 'Langkah penyelesaian benar dengan sedikit kekeliruan minor pada kalkulasi.',
                        'cukup' => 'Memahami alur dasar penyelesaian namun langkah perhitungan masih kurang lengkap.',
                        'perlu_bimbingan' => 'Belum mampu menentukan strategi penyelesaian masalah secara mandiri.',
                    ],
                    [
                        'aspek' => 'Partisipasi & Kolaborasi Kelompok',
                        'sangat_baik' => 'Sangat aktif berdiskusi, memimpin pemecahan masalah kelompok, menghargai pendapat orang lain.',
                        'baik' => 'Aktif berkontribusi dan bekerja sama dengan baik dalam menyelesaikan tugas kelompok.',
                        'cukup' => 'Cukup berpartisipasi saat diminta anggota kelompok namun pasif berinisiatif.',
                        'perlu_bimbingan' => 'Kurang fokus pada kerja kelompok dan perlu dorongan aktif dari guru.',
                    ],
                ],
                'sumatif' => [
                    'Tes tertulis berbasis uraian dan pilihan ganda kontekstual (Lingkup Materi).',
                    'Penilaian portofolio laporan hasil penyelidikan dan penyelesaian tugas pada LKPD.',
                ],
            ],
            'pengayaan_remedial' => [
                'pengayaan' => 'Peserta didik dengan capaian tinggi (di atas rata-rata) diberikan tugas eksplorasi materi tingkat lanjut berkarakter HOTS atau menjadi tutor sebaya bagi kelompoknya.',
                'remedial' => 'Peserta didik yang belum mencapai ketuntasan minimum diberikan bimbingan ulang secara individual/kelompok kecil pada indikator yang belum dipahami, dilanjutkan dengan tes remedial.',
            ],
            'refleksi' => [
                'guru' => [
                    'Apakah pemilihan model dan media pembelajaran sudah mampu mengaktifkan seluruh peserta didik?',
                    'Momen apa yang menjadi kendala utama peserta didik dalam memahami materi pertemuan ini?',
                    'Langkah apa yang perlu diperbaiki untuk meningkatkan efektivitas KBM pada pertemuan berikutnya?',
                ],
                'peserta_didik' => [
                    'Bagian materi mana yang menurutmu paling menarik dan paling mudah dipahami hari ini?',
                    'Bagian mana yang dirasa masih membingungkan atau memerlukan penjelasan ulang?',
                    'Bagaimana perasaanmu setelah mengikuti kegiatan pembelajaran bersama kelompokmu?',
                ],
            ],
            'lampiran' => [
                'bahan_bacaan' => 'Rangkuman materi intisari konsep pokok, istilah penting (glosarium), dan referensi literasi pendukung yang relevan.',
                'lkpd' => [
                    'judul' => 'LEMBAR KERJA PESERTA DIDIK (LKPD) - '.$tp['kode_tp'],
                    'petunjuk' => 'Bacalah setiap instruksi dengan seksama, diskusikan bersama anggota kelompokmu, dan tuliskan hasil temuanmu pada kolom lembar kerja yang disediakan.',
                    'teks_stimulus' => 'Diberikan sebuah kasus nyata di lingkungan madrasah terkait '.$tp['materi_pokok'].'. Pelajari dan analisis data yang tertera.',
                    'daftar_tugas' => [
                        'Identifikasi masalah utama yang terdapat dalam teks stimulus di atas.',
                        'Tuliskan langkah-langkah sistematis yang dapat diambil untuk memecahkan masalah tersebut.',
                        'Rumuskan hasil analisis kelompokmu ke dalam bentuk kesimpulan ringkas.',
                    ],
                ],
            ],
        ];
    }
}
