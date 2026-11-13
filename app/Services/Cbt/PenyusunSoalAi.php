<?php

namespace App\Services\Cbt;

use App\Enums\JenisSoal;
use App\Enums\LevelKognitif;
use App\Models\Soal;
use App\Services\Gemini\KlienGemini;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Drafts exam questions with Google Gemini from a test blueprint. Only the subject, grade, curriculum and topic
 * are sent; no student data. Every draft still needs a teacher's check before it is used.
 */
class PenyusunSoalAi
{
    public const int JUMLAH_MAKS = 10;

    protected const string TAG_DIIZINKAN = '<p><br><strong><b><em><i><u><ol><ul><li><sub><sup>';

    protected const string INSTRUKSI = <<<'TEKS'
        Anda adalah guru Madrasah Tsanawiyah (setingkat SMP) di Indonesia yang menyusun soal ujian sesuai kurikulum
        yang disebut di "konteks". Susun soal sebanyak "jumlah", jenis "jenis", tentang "materi".

        Aturan umum:
        - Bahasa Indonesia baku sesuai jenjang kelas. Mata pelajaran agama/bahasa Arab boleh memuat teks Arab berharakat.
        - Soal harus benar secara keilmuan, tidak ambigu, dan masing-masing berbeda.
        - "pertanyaan" berupa HTML sederhana: <p>, <br>, <strong>, <em>, <u>, <ol>, <ul>, <li>, <sub>, <sup>.
        - Jangan merujuk gambar karena soal dibuat tanpa gambar.
        - "level_kognitif" salah satu dari C1, C2, C3, C4, C5, C6. Bila "level_kognitif" di permintaan terisi, pakai level itu.
        - "pembahasan" menjelaskan singkat mengapa jawabannya benar.

        Aturan per jenis untuk "opsi":
        - pg: tepat 4 opsi, tepat satu "benar": true, pengecoh masuk akal. Jangan beri huruf A/B/C/D di teks.
        - pg_kompleks: 4 atau 5 opsi, 2 atau 3 di antaranya "benar": true. Tulis di pertanyaan bahwa jawaban benar lebih dari satu.
        - benar_salah: 3 sampai 5 pernyataan, masing-masing "benar": true atau false.
        - menjodohkan: 4 sampai 6 baris, "teks" pernyataan kiri dan "pasangan" jawaban kanan yang tepat.
        - esai: "opsi" kosong; "pembahasan" berisi kunci jawaban dan pedoman penskoran (poin per langkah/kata kunci).
        TEKS;

    public function __construct(protected KlienGemini $klien) {}

    /**
     * @param  array{mata_pelajaran: string, kelas: string, kurikulum: string, jenis: JenisSoal, jumlah: int, materi: string, indikator?: string|null, level_kognitif?: LevelKognitif|null}  $kisi
     * @return list<array{jenis: JenisSoal, materi: string, indikator: string|null, level_kognitif: LevelKognitif|null, pertanyaan: string, opsi: list<array<string, mixed>>, pembahasan: string|null}>
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when Gemini returns no usable question.
     */
    public function susun(array $kisi): array
    {
        $jenis = $kisi['jenis'];
        $jumlah = max(1, min(static::JUMLAH_MAKS, $kisi['jumlah']));

        $teks = $this->klien->hasilkan(
            static::INSTRUKSI,
            (string) json_encode([
                'konteks' => ['jenjang' => 'Madrasah Tsanawiyah', 'kurikulum' => $kisi['kurikulum'], 'mata_pelajaran' => $kisi['mata_pelajaran'], 'kelas' => $kisi['kelas']],
                'jenis' => $jenis->value,
                'jumlah' => $jumlah,
                'materi' => $kisi['materi'],
                'indikator' => $kisi['indikator'] ?? null,
                'level_kognitif' => ($kisi['level_kognitif'] ?? null)?->value,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            [
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'pertanyaan' => ['type' => 'STRING'],
                            'level_kognitif' => ['type' => 'STRING'],
                            'opsi' => ['type' => 'ARRAY', 'items' => [
                                'type' => 'OBJECT',
                                'properties' => ['teks' => ['type' => 'STRING'], 'benar' => ['type' => 'BOOLEAN'], 'pasangan' => ['type' => 'STRING']],
                                'required' => ['teks'],
                            ]],
                            'pembahasan' => ['type' => 'STRING'],
                        ],
                        'required' => ['pertanyaan', 'opsi'],
                    ],
                ],
            ],
            max(60, (int) config('services.gemini.timeout', 15)),
        );

        $hasil = [];

        foreach ((array) json_decode($teks, true) as $soal) {
            if (is_array($soal) && ($rapi = $this->rapikan($soal, $kisi)) !== null) {
                $hasil[] = $rapi;
            }
        }

        if ($hasil === []) {
            throw new RuntimeException('Gemini tidak mengembalikan soal yang lengkap.');
        }

        return array_slice($hasil, 0, $jumlah);
    }

    /**
     * The question in the shape the bank stores, or null when Gemini's answer key does not fit the kind.
     *
     * @param  array<array-key, mixed>  $soal
     * @param  array<string, mixed>  $kisi
     * @return array{jenis: JenisSoal, materi: string, indikator: string|null, level_kognitif: LevelKognitif|null, pertanyaan: string, opsi: list<array<string, mixed>>, pembahasan: string|null}|null
     */
    protected function rapikan(array $soal, array $kisi): ?array
    {
        /** @var JenisSoal $jenis */
        $jenis = $kisi['jenis'];
        $pertanyaan = trim(strip_tags((string) ($soal['pertanyaan'] ?? ''), static::TAG_DIIZINKAN));

        if ($pertanyaan === '') {
            return null;
        }

        $opsi = $jenis === JenisSoal::Esai ? [] : array_values(array_map(fn (mixed $baris): array => [
            'teks' => trim(strip_tags((string) (is_array($baris) ? ($baris['teks'] ?? '') : ''))),
            'gambar' => null,
            'benar' => is_array($baris) && ($baris['benar'] ?? false) === true,
            'pasangan' => $jenis === JenisSoal::Menjodohkan && is_array($baris) ? trim(strip_tags((string) ($baris['pasangan'] ?? ''))) : null,
        ], (array) ($soal['opsi'] ?? [])));

        $tidakLengkap = array_filter($opsi, fn (array $baris): bool => $baris['teks'] === '' || ($jenis === JenisSoal::Menjodohkan && blank($baris['pasangan'])));
        $pembahasan = trim(strip_tags((string) ($soal['pembahasan'] ?? '')));

        if ($tidakLengkap !== [] || Soal::kesalahanKunci($jenis, $opsi) !== null || ($jenis === JenisSoal::Esai && $pembahasan === '')) {
            return null;
        }

        return [
            'jenis' => $jenis,
            'materi' => $kisi['materi'],
            'indikator' => $kisi['indikator'] ?? null,
            'level_kognitif' => $kisi['level_kognitif'] ?? LevelKognitif::tryFrom(strtoupper(trim((string) ($soal['level_kognitif'] ?? '')))),
            'pertanyaan' => str_starts_with($pertanyaan, '<') ? $pertanyaan : '<p>'.e($pertanyaan).'</p>',
            'opsi' => $opsi,
            'pembahasan' => $pembahasan === '' ? null : $pembahasan,
        ];
    }
}
