<?php

namespace App\Services\Gemini;

use App\Models\AiApiKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Calls Google Gemini's generateContent REST API, trying each configured API key and model in turn while Gemini
 * is busy, out of quota, or not answering.
 *
 * The free tier may use requests to improve Google's products, so callers only send anonymous data.
 */
class KlienGemini
{
    public const string CACHE_KEY = 'gemini:kunci_aktif';

    protected const string ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * HTTP statuses that mean "try again later" rather than "this request is wrong".
     *
     * @var list<int>
     */
    protected const array STATUS_SEMENTARA = [429, 500, 503, 504];

    public static function aktif(): bool
    {
        return static::daftarKonfigurasi() !== [];
    }

    /**
     * The models from the primary active API key (or GEMINI_MODEL), tried in order.
     *
     * @return list<string>
     */
    public static function daftarModel(): array
    {
        $konfig = static::daftarKonfigurasi();

        return $konfig[0]['models'] ?? static::daftarModelEnv();
    }

    /**
     * @return list<string>
     */
    public static function daftarModelEnv(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('services.gemini.model')))));
    }

    /**
     * Active Gemini API key configurations ordered by priority, falling back to .env when none exist in DB.
     *
     * @return list<array{id: int|null, key: string, models: list<string>, timeout: int}>
     */
    public static function daftarKonfigurasi(): array
    {
        $dariDb = static::konfigurasiDatabase();
        if ($dariDb !== []) {
            return $dariDb;
        }

        $envKey = trim((string) config('services.gemini.key'));
        if ($envKey === '') {
            return [];
        }

        return [[
            'id' => null,
            'key' => $envKey,
            'models' => static::daftarModelEnv(),
            'timeout' => (int) config('services.gemini.timeout', 15),
        ]];
    }

    /**
     * @return list<array{id: int, key: string, models: list<string>, timeout: int}>
     */
    protected static function konfigurasiDatabase(): array
    {
        try {
            if (! Schema::hasTable('ai_api_keys')) {
                return [];
            }

            /** @var list<array{id: int, key: string, models: list<string>, timeout: int}> */
            return Cache::remember(static::CACHE_KEY, now()->addMinute(), function (): array {
                return AiApiKey::query()
                    ->where('provider', 'gemini')
                    ->where('is_aktif', true)
                    ->orderBy('prioritas')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (AiApiKey $k): array => [
                        'id' => $k->id,
                        'key' => trim($k->api_key),
                        'models' => $k->daftarModelArray() ?: static::daftarModelEnv(),
                        'timeout' => max(5, $k->timeout_detik),
                    ])
                    ->filter(fn (array $k): bool => $k['key'] !== '' && $k['models'] !== [])
                    ->values()
                    ->all();
            });
        } catch (Throwable) {
            return [];
        }
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
        $daftarKonfig = static::daftarKonfigurasi();

        foreach ($daftarKonfig as $index => $konfig) {
            $adaCadangan = $index < count($daftarKonfig) - 1;

            foreach ($konfig['models'] as $model) {
                try {
                    $hasil = $this->hasilkanDengan(
                        $model,
                        $instruksi,
                        $isi,
                        $generationConfig,
                        $timeout ?? $konfig['timeout'],
                        $konfig['key'],
                    );

                    $this->catatHasil($konfig['id'], true);

                    return $hasil;
                } catch (ConnectionException|RequestException $exception) {
                    $status = $exception instanceof RequestException ? $exception->response->status() : null;
                    $sementara = $exception instanceof ConnectionException || in_array($status, static::STATUS_SEMENTARA, true);

                    $this->catatHasil($konfig['id'], false, static::alasanGagal($exception));
                    $gagalTerakhir = $exception;

                    if (! $sementara && ! $adaCadangan) {
                        throw $exception;
                    }

                    if (! $sementara) {
                        break;
                    }
                }
            }
        }

        throw $gagalTerakhir ?? new RuntimeException('Tidak ada API key atau model Gemini yang dikonfigurasi.');
    }

    /**
     * Tests a single stored API key with a minimal prompt and updates its status.
     *
     * @return array{ok: bool, model: string, pesan: string}
     */
    public function ujiKoneksi(AiApiKey $kunci): array
    {
        $models = $kunci->daftarModelArray() ?: static::daftarModelEnv();
        $model = $models[0] ?? 'gemini-2.5-flash';

        try {
            $balasan = $this->hasilkanDengan(
                $model,
                'Balas hanya dengan satu kata: OK',
                'Ping koneksi',
                ['maxOutputTokens' => 16, 'temperature' => 0],
                min(10, max(5, $kunci->timeout_detik)),
                trim($kunci->api_key),
            );

            $this->catatHasil($kunci->id, true);

            return [
                'ok' => true,
                'model' => $model,
                'pesan' => "Koneksi berhasil ({$model}). Respons: {$balasan}",
            ];
        } catch (Throwable $exception) {
            $alasan = static::alasanGagal($exception);
            $this->catatHasil($kunci->id, false, $alasan);

            return [
                'ok' => false,
                'model' => $model,
                'pesan' => $alasan,
            ];
        }
    }

    protected function catatHasil(?int $kunciId, bool $berhasil, ?string $error = null): void
    {
        if ($kunciId === null) {
            return;
        }

        $update = [
            'terakhir_dipakai_at' => now(),
            'status_terakhir' => $berhasil ? 'ok' : 'error',
            'pesan_error_terakhir' => $berhasil ? null : $error,
        ];

        $query = AiApiKey::query()->whereKey($kunciId);

        if ($berhasil) {
            $query->increment('total_permintaan', 1, $update);
        } else {
            $query->update($update);
        }
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     */
    protected function hasilkanDengan(
        string $model,
        string $instruksi,
        string $isi,
        array $generationConfig,
        int $timeout,
        ?string $apiKey = null,
    ): string {
        $respons = Http::withHeaders(['x-goog-api-key' => $apiKey ?? (string) config('services.gemini.key')])
            ->acceptJson()
            ->timeout($timeout)
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
