<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evidence item tagged to one of the 14 BAN-PDM indicators (butir_01..butir_14).
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property string $kode_butir
 * @property string $judul
 * @property string $sumber_tipe
 * @property int|null $sumber_id
 * @property string|null $berkas_path
 * @property string|null $keterangan
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 */
#[Fillable([
    'tahun_ajaran_id',
    'kode_butir',
    'judul',
    'sumber_tipe',
    'sumber_id',
    'berkas_path',
    'keterangan',
    'user_id',
])]
class AkreditasiBukti extends Model
{
    protected function casts(): array
    {
        return [
            'sumber_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
