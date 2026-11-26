<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $nama
 * @property string $kategori
 * @property int|null $mata_pelajaran_id
 * @property int $jumlah
 * @property bool $kondisi_baik
 * @property bool $digunakan_siswa
 * @property string|null $keterangan
 * @property string|null $berkas_path
 * @property-read MataPelajaran|null $mataPelajaran
 */
#[Guarded(['id'])]
class InventarisMedia extends Model
{
    /** @var array<string, string> */
    public const array KATEGORI = [
        'non_digital' => 'Media / Alat Peraga Non-Digital',
        'digital' => 'Media Pembelajaran Digital / TIK',
        'buku_cetak' => 'Buku Teks / Bacaan Cetak',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'kondisi_baik' => 'boolean',
            'digunakan_siswa' => 'boolean',
        ];
    }

    /** @return BelongsTo<MataPelajaran, $this> */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
