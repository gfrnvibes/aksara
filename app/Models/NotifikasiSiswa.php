<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $siswa_id
 * @property string $kategori
 * @property string $judul
 * @property string $isi
 * @property array<string, mixed>|null $data
 * @property Carbon|null $dibaca_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Siswa $siswa
 */
#[Fillable(['siswa_id', 'kategori', 'judul', 'isi', 'data', 'dibaca_at'])]
class NotifikasiSiswa extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'dibaca_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
