<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property string $judul
 * @property string $status
 * @property string|null $ringkasan_ai_html
 * @property int|null $pembuat_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read User|null $pembuat
 * @property-read Collection<int, EdmIndikator> $indikators
 */
#[Fillable([
    'tahun_ajaran_id',
    'judul',
    'status',
    'ringkasan_ai_html',
    'pembuat_id',
])]
class EdmEvaluasi extends Model
{
    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembuat_id');
    }

    /** @return HasMany<EdmIndikator, $this> */
    public function indikators(): HasMany
    {
        return $this->hasMany(EdmIndikator::class);
    }

    public function jumlahTerisi(): int
    {
        return $this->indikators->filter(fn (EdmIndikator $i): bool => $i->skor_final !== null)->count();
    }

    public function rataRataSkor(): ?float
    {
        $skor = $this->indikators->pluck('skor_final')->filter(fn ($v): bool => $v !== null);
        if ($skor->isEmpty()) {
            return null;
        }

        return round((float) $skor->avg(), 2);
    }

    public function rataRataAspek(string $aspek): ?float
    {
        $skor = $this->indikators
            ->where('aspek', $aspek)
            ->pluck('skor_final')
            ->filter(fn ($v): bool => $v !== null);

        if ($skor->isEmpty()) {
            return null;
        }

        return round((float) $skor->avg(), 2);
    }
}
