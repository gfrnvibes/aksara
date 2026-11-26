<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $edm_indikator_id
 * @property string $judul
 * @property string $berkas_path
 * @property string|null $keterangan
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EdmIndikator $indikator
 * @property-read User|null $user
 */
#[Fillable([
    'edm_indikator_id',
    'judul',
    'berkas_path',
    'keterangan',
    'user_id',
])]
class EdmBukti extends Model
{
    /** @return BelongsTo<EdmIndikator, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(EdmIndikator::class, 'edm_indikator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
