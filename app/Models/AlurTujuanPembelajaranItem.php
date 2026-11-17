<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Butir baris dalam Alur Tujuan Pembelajaran (ATP).
 *
 * @property int $id
 * @property int $alur_tujuan_pembelajaran_id
 * @property int $urutan
 * @property string $elemen_cp
 * @property string $capaian_pembelajaran
 * @property string $tujuan_pembelajaran
 * @property string $materi_pembelajaran
 * @property string $alur_tujuan_pembelajaran
 * @property int $alokasi_waktu_jp
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AlurTujuanPembelajaran $alurTujuanPembelajaran
 */
#[Fillable([
    'alur_tujuan_pembelajaran_id', 'urutan', 'elemen_cp', 'capaian_pembelajaran',
    'tujuan_pembelajaran', 'materi_pembelajaran', 'alur_tujuan_pembelajaran',
    'alokasi_waktu_jp', 'keterangan',
])]
class AlurTujuanPembelajaranItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'alokasi_waktu_jp' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AlurTujuanPembelajaran, $this>
     */
    public function alurTujuanPembelajaran(): BelongsTo
    {
        return $this->belongsTo(AlurTujuanPembelajaran::class);
    }
}
