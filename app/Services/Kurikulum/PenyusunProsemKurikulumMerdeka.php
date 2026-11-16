<?php

namespace App\Services\Kurikulum;

use App\Enums\Semester;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\ProgramSemester;
use App\Models\ProgramSemesterItem;
use App\Models\ProgramTahunan;
use App\Services\Gemini\KlienGemini;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * @phpstan-type ProsemRow array{urutan: int, tujuan_pembelajaran: string, materi_pokok: string, alokasi_waktu_jp: int, bulan: string, minggu_ke: string, distribusi_minggu: array<string, list<int>>|null, keterangan: string|null}
 */
class PenyusunProsemKurikulumMerdeka
{
    public function __construct(protected ?KlienGemini $klien = null)
    {
        $this->klien ??= app(KlienGemini::class);
    }

    /**
     * @return list<string>
     */
    public static function daftarBulan(int $semester): array
    {
        return $semester === Semester::Genap->value
            ? ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni']
            : ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }

    /**
     * Generate complete PROSEM directly with Gemini AI or standard fallback.
     *
     * @return array{catatan: string, items: list<ProsemRow>}
     */
    public function susunProsemLengkap(
        int $userId,
        string $namaMapel,
        string $namaKelas,
        string $fase,
        int $semester,
        int $jpPerMinggu = 4,
        ?string $instruksiKhusus = null
    ): array {
        $labelSemester = $semester === Semester::Genap->value ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
        $daftarBulan = static::daftarBulan($semester);
        $bulanStr = implode(', ', $daftarBulan);

        if (! KlienGemini::aktif()) {
            return $this->dapatkanDrafStandar($namaMapel, $fase, $semester, $jpPerMinggu);
        }

        $limit = 'prosem-ai-user:'.$userId;
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            return $this->dapatkanDrafStandar($namaMapel, $fase, $semester, $jpPerMinggu);
        }
        RateLimiter::hit($limit, 600);

        $promptSistem = 'Anda adalah konsultan dan pakar perencanaan pembelajaran resmi Kurikulum Merdeka Kemendikbudristek & Kemenag RI. '
            .'Tugas Anda adalah merancang Program Semester (PROSEM / PROMES) secara lengkap dan terperinci untuk satu semester penuh. '
            .'Aturan Kurikulum Merdeka untuk PROSEM: '
            ."1. Semester yang disusun: {$labelSemester}. Bulan yang tercakup: {$bulanStr}. "
            ."2. Setiap bulan memiliki 5 minggu. Alokasi JP per minggu: {$jpPerMinggu} JP. "
            .'3. Kalender pendidikan efektif: '
            .($semester === 1
                ? 'Juli: M1-M2 libur/MPLS, M3-M4 KBM efektif (8 JP). Agustus: M1-M4 KBM efektif (16 JP). September: M1-M3 KBM efektif (12 JP), M4 Sumatif Tengah Semester / STS (4 JP). Oktober: M1-M4 KBM efektif / P5 (16 JP). November: M1-M4 KBM efektif (16 JP). Desember: M1-M2 Sumatif Akhir Semester / SAS (8 JP), M3 Pengolahan Nilai & Rapor, M4 Libur Semester 1.'
                : 'Januari: M1-M4 KBM efektif (16 JP). Februari: M1-M4 KBM efektif (16 JP). Maret: M1-M3 KBM efektif (12 JP), M4 Sumatif Tengah Semester / Awal Ramadhan (4 JP). April: M1-M2 Idul Fitri / KBM, M3-M4 KBM efektif (8-16 JP). Mei: M1-M4 KBM efektif (16 JP). Juni: M1-M2 Sumatif Akhir Tahun / SAT (8 JP), M3 Pengolahan Nilai & Rapor, M4 Libur Kenaikan Kelas.'
            ).' '
            .'4. Setiap baris materi harus memiliki: '
            .'   - urutan: integer '
            .'   - tujuan_pembelajaran: Rumusan operasional Tujuan Pembelajaran (TP) yang jelas dan terukur. '
            .'   - materi_pokok: Pokok bahasan / lingkup materi. '
            .'   - alokasi_waktu_jp: Jumlah jam pelajaran (kelipatan realistis sesuai JP mingguan). '
            .'   - bulan: Nama bulan pelaksanaan (contoh: "Juli", "Agustus - September"). '
            .'   - minggu_ke: Rincian minggu ke- berapa (contoh: "Minggu ke-3, 4", "Minggu ke-1 s.d. 3"). '
            .'   - distribusi_minggu: Object pemetaan untuk setiap 6 bulan ('.implode(', ', array_map(fn ($b) => "\"{$b}\"", $daftarBulan)).'), masing-masing berupa array 5 angka integer merepresentasikan jam pelajaran di Minggu 1 s.d. 5 (contoh: [0, 0, 4, 4, 0]). '
            .'   - keterangan: Jenis asesmen (contoh: Formatif, Sumatif Lingkup Materi 1, STS, SAS/SAT, P5, Cadangan). '
            .'5. Tuliskan ringkasan "catatan" (2-3 kalimat) mengenai strategi pelaksanaan semester ini. '
            .'Keluarkan format JSON murni dengan skema: '
            .'{ "catatan": "string", "items": [ { "urutan": 1, "tujuan_pembelajaran": "string", "materi_pokok": "string", "alokasi_waktu_jp": 12, "bulan": "string", "minggu_ke": "string", "distribusi_minggu": { ... }, "keterangan": "string" } ] }';

        $promptUser = json_encode(array_filter([
            'mata_pelajaran' => $namaMapel,
            'kelas' => $namaKelas,
            'fase' => $fase,
            'semester' => $semester,
            'label_semester' => $labelSemester,
            'jp_per_minggu' => $jpPerMinggu,
            'daftar_bulan' => $daftarBulan,
            'instruksi_khusus' => $instruksiKhusus,
        ]), JSON_THROW_ON_ERROR);

        try {
            $hasil = $this->klien->hasilkan(
                $promptSistem,
                $promptUser,
                ['responseMimeType' => 'application/json'],
                max(50, (int) config('services.gemini.timeout', 50)),
            );

            $data = json_decode($hasil, true);
            $rawItems = $data['items'] ?? (is_array($data) && array_is_list($data) ? $data : []);
            $catatan = ! empty($data['catatan']) && is_string($data['catatan'])
                ? $data['catatan']
                : "Program Semester disusun berdasarkan Capaian Pembelajaran Kurikulum Merdeka untuk {$namaMapel} Kelas {$namaKelas} ({$labelSemester}).";

            if (empty($rawItems)) {
                return $this->dapatkanDrafStandar($namaMapel, $fase, $semester, $jpPerMinggu);
            }

            $bersih = [];
            foreach ($rawItems as $idx => $row) {
                /** @var array<string, list<int>>|null $distribusi */
                $distribusi = is_array($row['distribusi_minggu'] ?? null) ? $row['distribusi_minggu'] : null;

                $bersih[] = [
                    'urutan' => (int) ($row['urutan'] ?? ($idx + 1)),
                    'tujuan_pembelajaran' => (string) ($row['tujuan_pembelajaran'] ?? 'Peserta didik memahami capaian pembelajaran.'),
                    'materi_pokok' => (string) ($row['materi_pokok'] ?? 'Materi Pembelajaran'),
                    'alokasi_waktu_jp' => max(1, (int) ($row['alokasi_waktu_jp'] ?? $jpPerMinggu)),
                    'bulan' => (string) ($row['bulan'] ?? $daftarBulan[0]),
                    'minggu_ke' => (string) ($row['minggu_ke'] ?? 'Minggu ke-'.(($idx % 4) + 1)),
                    'distribusi_minggu' => $distribusi,
                    'keterangan' => ! empty($row['keterangan']) ? (string) $row['keterangan'] : 'Sumatif Lingkup Materi',
                ];
            }

            return [
                'catatan' => $catatan,
                'items' => $bersih,
            ];
        } catch (Throwable) {
            return $this->dapatkanDrafStandar($namaMapel, $fase, $semester, $jpPerMinggu);
        }
    }

    /**
     * Create or update a complete ProgramSemester model in one call.
     */
    public function buatProsemLengkap(
        int $userId,
        Gtk $gtk,
        int $tahunAjaranId,
        int $kelasId,
        int $mataPelajaranId,
        int $semester,
        string $fase,
        int $jpPerMinggu = 4,
        ?string $instruksiKhusus = null,
        ?int $protaId = null
    ): ProgramSemester {
        $kelas = Kelas::query()->findOrFail($kelasId);
        $mapel = MataPelajaran::query()->findOrFail($mataPelajaranId);

        $hasil = $this->susunProsemLengkap(
            $userId,
            $mapel->nama,
            $kelas->nama,
            $fase,
            $semester,
            $jpPerMinggu,
            $instruksiKhusus
        );

        $prosem = ProgramSemester::query()->updateOrCreate(
            [
                'tahun_ajaran_id' => $tahunAjaranId,
                'semester' => $semester,
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $mataPelajaranId,
            ],
            [
                'gtk_id' => $gtk->id,
                'program_tahunan_id' => $protaId,
                'fase' => $fase,
                'jp_per_minggu' => $jpPerMinggu,
                'total_minggu_efektif' => $semester === Semester::Genap->value ? 16 : 18,
                'catatan' => $hasil['catatan'],
            ]
        );

        $this->terapkanKeProsem($prosem, $hasil['items']);

        return $prosem;
    }

    /**
     * Import and distribute items from an existing PROTA.
     *
     * @return array{catatan: string, items: list<ProsemRow>}
     */
    public function salinDariProta(ProgramTahunan $prota, int $semester, int $jpPerMinggu = 4): array
    {
        $protaItems = $prota->items()->where('semester', $semester)->orderBy('urutan')->get();
        $daftarBulan = static::daftarBulan($semester);
        $jumlahBulan = count($daftarBulan);

        $items = [];
        $totalItems = $protaItems->count();

        foreach ($protaItems as $idx => $pItem) {
            $bulanIdx = $totalItems > 0 ? (int) floor(($idx / $totalItems) * $jumlahBulan) : 0;
            $bulanIdx = min($bulanIdx, $jumlahBulan - 1);
            $bulanNama = $daftarBulan[$bulanIdx];
            $slot = (int) (abs($idx) % 4);
            $mingguKe = 'Minggu ke-'.($slot + 1);

            $distBulan = [0, 0, 0, 0, 0];
            $distBulan[$slot] = $pItem->alokasi_waktu_jp;

            /** @var array<string, list<int>> $distribusi */
            $distribusi = [];
            foreach ($daftarBulan as $b) {
                $distribusi[$b] = $b === $bulanNama ? $distBulan : [0, 0, 0, 0, 0];
            }

            $items[] = [
                'urutan' => $idx + 1,
                'tujuan_pembelajaran' => $pItem->tujuan_pembelajaran,
                'materi_pokok' => $pItem->materi_pokok,
                'alokasi_waktu_jp' => $pItem->alokasi_waktu_jp,
                'bulan' => $bulanNama,
                'minggu_ke' => $mingguKe,
                'distribusi_minggu' => $distribusi,
                'keterangan' => $pItem->keterangan ?: 'Sumatif Lingkup Materi '.($idx + 1),
            ];
        }

        $semLabel = $semester === Semester::Genap->value ? 'Genap' : 'Ganjil';

        return [
            'catatan' => "Program Semester dijabarkan langsung dari Program Tahunan (PROTA) mata pelajaran {$prota->mataPelajaran->nama} Kelas {$prota->kelas->nama} untuk Semester {$semLabel}.",
            'items' => $items,
        ];
    }

    /**
     * Apply items list to a ProgramSemester model.
     *
     * @param  list<ProsemRow>  $items
     */
    public function terapkanKeProsem(ProgramSemester $prosem, array $items): void
    {
        $prosem->items()->delete();

        foreach ($items as $item) {
            ProgramSemesterItem::query()->create([
                'program_semester_id' => $prosem->id,
                'urutan' => $item['urutan'],
                'tujuan_pembelajaran' => $item['tujuan_pembelajaran'],
                'materi_pokok' => $item['materi_pokok'],
                'alokasi_waktu_jp' => $item['alokasi_waktu_jp'],
                'bulan' => $item['bulan'],
                'minggu_ke' => $item['minggu_ke'],
                'distribusi_minggu' => $item['distribusi_minggu'],
                'keterangan' => $item['keterangan'] ?? null,
            ]);
        }
    }

    /**
     * Realistic built-in default PROSEM template.
     *
     * @return array{catatan: string, items: list<ProsemRow>}
     */
    public function dapatkanDrafStandar(string $namaMapel, string $fase, int $semester, int $jpPerMinggu = 4): array
    {
        $daftarBulan = static::daftarBulan($semester);
        $semLabel = $semester === Semester::Genap->value ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';

        // Pull standard PROTA rows and map to months
        $protaService = app(PenyusunProtaKurikulumMerdeka::class);
        $protaRows = $protaService->dapatkanDrafStandar($namaMapel, $fase, $jpPerMinggu);
        $semesterRows = array_values(array_filter($protaRows, fn ($r) => $r['semester'] === $semester));

        $items = [];
        $total = count($semesterRows);

        foreach ($semesterRows as $idx => $row) {
            $bulanIdx = $total > 0 ? (int) floor(($idx / $total) * 6) : 0;
            $bulanIdx = min($bulanIdx, 5);
            $bulanNama = $daftarBulan[$bulanIdx];
            $mingguSlot = ($idx % 4) + 1;

            $distBulan = [0, 0, 0, 0, 0];
            $distBulan[$mingguSlot - 1] = $row['alokasi_waktu_jp'];

            /** @var array<string, list<int>> $distribusi */
            $distribusi = [];
            foreach ($daftarBulan as $b) {
                $distribusi[$b] = $b === $bulanNama ? $distBulan : [0, 0, 0, 0, 0];
            }

            $items[] = [
                'urutan' => $idx + 1,
                'tujuan_pembelajaran' => $row['tujuan_pembelajaran'],
                'materi_pokok' => $row['materi_pokok'],
                'alokasi_waktu_jp' => $row['alokasi_waktu_jp'],
                'bulan' => $bulanNama,
                'minggu_ke' => "Minggu ke-{$mingguSlot}",
                'distribusi_minggu' => $distribusi,
                'keterangan' => $row['keterangan'] ?? 'Sumatif Lingkup Materi '.($idx + 1),
            ];
        }

        return [
            'catatan' => "Program Semester disusun berdasarkan alokasi waktu efektif kalender pendidikan Kurikulum Merdeka untuk mata pelajaran {$namaMapel} ({$semLabel}).",
            'items' => $items,
        ];
    }
}
