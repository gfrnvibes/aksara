<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Calls Google Gemini's generateContent REST API, trying each configured model in turn while Gemini is busy, out
 * of quota, or not answering.
 *
 * The free tier may use requests to improve Google's products, so callers only send anonymous data.
 */
class KlienGemini
{
    protected const string ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * HTTP statuses that mean "try again later" rather than "this request is wrong".
     *
     * @var list<int>
     */
    protected const array STATUS_SEMENTARA = [429, 500, 503, 504];

    public static function aktif(): bool
    {
        return filled(config('services.gemini.key'));
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
     * A short Indonesian explanation of why Gemini could not be used, for the admin.
     */
    public static function alasanGagal(Throwable $exception): string
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return match (true) {
            $exception instanceof ConnectionException => 'Gemini tidak merespons atau koneksi internet terputus',
            $status === 429 => 'kuota gratis Gemini sedang habis',
            in_array($status, [500, 503, 504], true) => 'server Gemini sedang sibuk',
            in_array($status, [400, 401, 403], true) => 'API key atau nama model Gemini ditolak; periksa GEMINI_API_KEY dan GEMINI_MODEL',
            default => 'Gemini tidak dapat menyusun teks',
        };
    }

    /**
     * The text Gemini writes for the instruction and the content.
     *
     * @param  array<string, mixed>  $generationConfig
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when Gemini blocks the request or returns no text.
     */
    public function hasilkan(string $instruksi, string $isi, array $generationConfig = [], ?int $timeout = null): string
    {
        $gagalTerakhir = null;

        foreach (static::daftarModel() as $model) {
            try {
                return $this->hasilkanDengan($model, $instruksi, $isi, $generationConfig, $timeout);
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
     * @param  array<string, mixed>  $generationConfig
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     */
    protected function hasilkanDengan(string $model, string $instruksi, string $isi, array $generationConfig, ?int $timeout): string
    {
        $respons = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->acceptJson()
            ->timeout($timeout ?? (int) config('services.gemini.timeout', 15))
            ->post(sprintf(static::ENDPOINT, $model), [
                'systemInstruction' => ['parts' => [['text' => $instruksi]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $isi]]]],
                'generationConfig' => ['maxOutputTokens' => 2048, ...$generationConfig],
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

        return $teks;
    }
}
