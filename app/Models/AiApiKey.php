<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Services\Gemini\KlienGemini;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Stored AI provider API key with failover priority, model list, and usage telemetry.
 *
 * @property int $id
 * @property string $nama
 * @property string $provider
 * @property string $api_key
 * @property string $daftar_model
 * @property int $timeout_detik
 * @property int $prioritas
 * @property bool $is_aktif
 * @property int $total_permintaan
 * @property Carbon|null $terakhir_dipakai_at
 * @property string|null $status_terakhir
 * @property string|null $pesan_error_terakhir
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nama',
    'provider',
    'api_key',
    'daftar_model',
    'timeout_detik',
    'prioritas',
    'is_aktif',
    'total_permintaan',
    'terakhir_dipakai_at',
    'status_terakhir',
    'pesan_error_terakhir',
])]
class AiApiKey extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'timeout_detik' => 'integer',
            'prioritas' => 'integer',
            'is_aktif' => 'boolean',
            'total_permintaan' => 'integer',
            'terakhir_dipakai_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(KlienGemini::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(KlienGemini::CACHE_KEY));
    }

    /**
     * @return list<string>
     */
    public function daftarModelArray(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->daftar_model))));
    }

    public function kunciTersamar(): string
    {
        $key = trim($this->api_key);
        $len = mb_strlen($key);

        if ($len <= 8) {
            return str_repeat('•', max(4, $len));
        }

        return mb_substr($key, 0, 6).'••••••'.mb_substr($key, -4);
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
