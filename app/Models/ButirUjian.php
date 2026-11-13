<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question of an exam, its place in packet A and its score weight.
 *
 * @property int $id
 * @property int $ujian_id
 * @property int $soal_id
 * @property int $urutan
 * @property int $bobot
 * @property-read Ujian $ujian
 * @property-read Soal $soal
 */
#[Fillable(['ujian_id', 'soal_id', 'urutan', 'bobot'])]
class ButirUjian extends Model
{
    protected function casts(): array
    {
        return ['urutan' => 'integer', 'bobot' => 'integer'];
    }

    /**
     * @return BelongsTo<Ujian, $this>
     */
    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    /**
     * @return BelongsTo<Soal, $this>
     */
    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }
}
