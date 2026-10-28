<?php

namespace App\Services\CapaianKompetensi;

use App\Services\Gemini\KlienGemini;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Writes competence descriptions with Google Gemini. Only the subject, the mastery level and the class level are
 * sent — never a student's name or score.
 */
class CapaianGemini
{
    protected const string INSTRUKSI = <<<'TEKS'
        Anda adalah guru mata pelajaran di sebuah Madrasah Tsanawiyah yang memakai Kurikulum Merdeka (KMA 450/2024).
        Untuk setiap item pada data JSON, tulis deskripsi capaian kompetensi rapor.

        Aturan:
        - Satu kalimat dalam Bahasa Indonesia baku, paling banyak 30 kata.
        - Awali persis dengan teks pada "awalan", lalu lanjutkan dengan kompetensi atau materi yang dicapai.
        - Bila "materi" diisi, gunakan materi itu. Bila kosong, sebut materi yang lazim dipelajari pada mata pelajaran
          tersebut untuk kelas, fase, dan semester yang diberikan.
        - Jangan menyebut angka nilai, nama siswa, peringkat, atau nama madrasah.
        - Kembalikan JSON array: satu objek {"kunci": ..., "capaian": ...} untuk setiap item, dengan "kunci" yang sama.
        TEKS;

    public function __construct(protected KlienGemini $klien) {}

    /**
     * @param  list<array{kunci: string, mata_pelajaran: string, awalan: string, materi: string|null}>  $daftar
     * @param  array{kelas: string|null, fase: string|null, semester: string|null}  $konteks
     * @return array<string, string> description per key; keys Gemini left out are missing
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when Gemini blocks the request or returns no usable JSON.
     */
    public function buat(array $daftar, array $konteks): array
    {
        $teks = $this->klien->hasilkan(
            static::INSTRUKSI,
            (string) json_encode(['konteks' => $konteks, 'item' => $daftar], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            [
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => ['kunci' => ['type' => 'STRING'], 'capaian' => ['type' => 'STRING']],
                        'required' => ['kunci', 'capaian'],
                    ],
                ],
            ],
            max(30, (int) config('services.gemini.timeout', 15)),
        );

        $hasil = json_decode($teks, true);

        if (! is_array($hasil)) {
            throw new RuntimeException('Gemini tidak mengembalikan JSON yang valid.');
        }

        $capaian = [];

        foreach ($hasil as $baris) {
            if (is_array($baris) && is_string($baris['kunci'] ?? null) && is_string($baris['capaian'] ?? null) && filled($baris['capaian'])) {
                $capaian[$baris['kunci']] = (string) preg_replace('/\s+/', ' ', trim($baris['capaian']));
            }
        }

        return $capaian;
    }
}
