<?php

namespace App\Services\CatatanWaliKelas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Writes the homeroom note with Google Gemini (generateContent REST API).
 *
 * Only the anonymous summary is sent; the free tier may use requests to improve Google's products.
 */
class CatatanGemini implements PembuatCatatan
{
    protected const string ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    protected const string INSTRUKSI = <<<'TEKS'
        Anda adalah wali kelas di sebuah Madrasah Tsanawiyah. Tulis catatan wali kelas untuk rapor satu semester
        berdasarkan data JSON yang diberikan.

        Aturan:
        - Bahasa Indonesia yang santun, hangat, dan memotivasi; 3 sampai 5 kalimat dalam satu paragraf.
        - Sebut siswa sebagai "Ananda" di awal kalimat pertama, lalu "ananda" bila perlu. Jangan menulis nama.
        - Hanya gunakan fakta dari data: sebutkan kekuatan, mata pelajaran yang masih di bawah KKM (bila ada),
          ekstrakurikuler, akhlak dan kepribadian, serta kehadiran bila ada yang perlu diperhatikan.
        - Jangan mengarang nilai, peringkat, atau kejadian yang tidak ada di data.
        - Bila "keputusan_akhir_tahun" terisi, tutup catatan dengan keputusan itu: ucapkan selamat untuk naik kelas
          (sebutkan kelas tujuannya bila ada) atau kelulusan. Untuk "Tinggal di Kelas", sampaikan dengan nada yang
          menguatkan dan tidak menghakimi, serta ajak ananda dan orang tua untuk bersama-sama memperbaikinya.
          Bila kosong, jangan menyebut kenaikan kelas sama sekali.
        - Tulis teks biasa tanpa judul, tanpa poin-poin, dan tanpa format markdown.
        TEKS;

    /**
     * HTTP statuses that mean "try again later" rather than "this request is wrong".
     *
     * @var list<int>
     */
    protected const array STATUS_SEMENTARA = [429, 500, 503, 504];

    /**
     * Try each configured model in order, moving on while Gemini is busy, out of quota, or not answering.
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when Gemini blocks the request or returns no text.
     */
    public function buat(RingkasanRapor $ringkasan): string
    {
        $daftarModel = static::daftarModel();
        $gagalTerakhir = null;

        foreach ($daftarModel as $model) {
            try {
                return $this->buatDengan($model, $ringkasan);
            } catch (ConnectionException|RequestException $exception) {
                if ($exception instanceof RequestException && ! in_array($exception->response->status(), static::STATUS_SEMENTARA, true)) {
                    throw $exception;
                }

                $gagalTerakhir = $exception;
            }
        }

        throw $gagalTerakhir ?? new RuntimeException('Tidak ada model Gemini yang dikonfigurasi.');
    }

    /**
     * The models from GEMINI_MODEL, a comma-separated list tried in order.
     *
     * @return list<string>
     */
    public static function daftarModel(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('services.gemini.model')))));
    }

    /**
     * A short Indonesian explanation of why Gemini could not write the note, for the admin.
     */
    public static function alasanGagal(Throwable $exception): string
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return match (true) {
            $exception instanceof ConnectionException => 'Gemini tidak merespons atau koneksi internet terputus',
            $status === 429 => 'kuota gratis Gemini sedang habis',
            in_array($status, [500, 503, 504], true) => 'server Gemini sedang sibuk',
            in_array($status, [400, 401, 403], true) => 'API key atau nama model Gemini ditolak; periksa GEMINI_API_KEY dan GEMINI_MODEL',
            default => 'Gemini tidak dapat menyusun catatan',
        };
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     */
    protected function buatDengan(string $model, RingkasanRapor $ringkasan): string
    {
        $respons = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->acceptJson()
            ->timeout((int) config('services.gemini.timeout', 15))
            ->post(sprintf(static::ENDPOINT, $model), [
                'systemInstruction' => ['parts' => [['text' => static::INSTRUKSI]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => json_encode($ringkasan->untukAi(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
                ]],
                'generationConfig' => ['maxOutputTokens' => 2048],
            ])
            ->throw();

        if (filled($alasan = $respons->json('promptFeedback.blockReason'))) {
            throw new RuntimeException("Gemini menolak permintaan ({$alasan}).");
        }

        $teks = '';

        foreach ((array) $respons->json('candidates.0.content.parts', []) as $bagian) {
            $teks .= is_array($bagian) && is_string($bagian['text'] ?? null) ? $bagian['text'] : '';
        }

        $teks = trim($teks);

        if ($teks === '') {
            throw new RuntimeException('Gemini tidak mengembalikan teks (finishReason: '.$respons->json('candidates.0.finishReason', '-').').');
        }

        return preg_replace('/\s+/', ' ', $teks);
    }
}
