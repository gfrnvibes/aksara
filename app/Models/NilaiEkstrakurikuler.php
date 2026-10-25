<?php

namespace App\Models;

use Database\Factories\NilaiEkstrakurikulerFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rapor_id
 * @property int $ekstrakurikuler_id
 * @property string|null $nilai
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id'])]
class NilaiEkstrakurikuler extends Model
{
    /** @use HasFactory<NilaiEkstrakurikulerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Rapor, $this>
     */
    public function rapor(): BelongsTo
    {
        return $this->belongsTo(Rapor::class);
    }

    /**
     * @return BelongsTo<Ekstrakurikuler, $this>
     */
    public function ekstrakurikuler(): BelongsTo
    {
        return $this->belongsTo(Ekstrakurikuler::class);
    }
}
