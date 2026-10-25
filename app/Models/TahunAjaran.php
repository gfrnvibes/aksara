<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'is_aktif'])]
class TahunAjaran extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<TahunAjaranFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * Ensure only one academic year is marked as active.
     */
    protected static function booted(): void
    {
        static::saved(function (TahunAjaran $tahunAjaran): void {
            if ($tahunAjaran->is_aktif) {
                static::query()
                    ->whereKeyNot($tahunAjaran->getKey())
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }
        });
    }

    /**
     * @return HasMany<Rapor, $this>
     */
    public function rapor(): HasMany
    {
        return $this->hasMany(Rapor::class);
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
