<?php

namespace App\Services\CatatanWaliKelas;

use Illuminate\Support\Str;
use Throwable;

/**
 * Picks the note writer: Gemini when an API key is configured, the rules otherwise or when Gemini fails.
 */
class PenyusunCatatanWaliKelas
{
    public const string SUMBER_GEMINI = 'gemini';

    public const string SUMBER_ATURAN = 'aturan';

    public function __construct(
        protected CatatanGemini $gemini,
        protected CatatanBerbasisAturan $aturan,
    ) {}

    public static function geminiAktif(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @return array{teks: string, sumber: string, alasanGagalAi: string|null}
     */
    public function susun(RingkasanRapor $ringkasan, string $sapaan): array
    {
        if (static::geminiAktif()) {
            try {
                return ['teks' => static::sapa($this->gemini->buat($ringkasan), $sapaan), 'sumber' => static::SUMBER_GEMINI, 'alasanGagalAi' => null];
            } catch (Throwable $exception) {
                report($exception);

                return ['teks' => static::sapa($this->aturan->buat($ringkasan), $sapaan), 'sumber' => static::SUMBER_ATURAN, 'alasanGagalAi' => CatatanGemini::alasanGagal($exception)];
            }
        }

        return ['teks' => static::sapa($this->aturan->buat($ringkasan), $sapaan), 'sumber' => static::SUMBER_ATURAN, 'alasanGagalAi' => null];
    }

    /**
     * Put the student's nickname into the first "Ananda", which is the only place the note names them.
     */
    protected static function sapa(string $teks, string $sapaan): string
    {
        return Str::replaceFirst('Ananda', $sapaan, $teks);
    }
}
