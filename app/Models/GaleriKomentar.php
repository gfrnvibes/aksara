<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Komentar publik pada item galeri dokumentasi kegiatan siswa.
 *
 * @property int $id
 * @property int $galeri_kegiatan_id
 * @property string $nama_pengomentar
 * @property string|null $peran
 * @property string $isi
 * @property bool $is_tampil
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GaleriKegiatan $galeriKegiatan
 */
#[Fillable(['galeri_kegiatan_id', 'nama_pengomentar', 'peran', 'isi', 'is_tampil', 'ip_address'])]
class GaleriKomentar extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_tampil' => 'boolean',
        ];
    }

    /**
     * @param  Builder<GaleriKomentar>  $query
     */
    #[Scope]
    protected function tampil(Builder $query): void
    {
        $query->where('is_tampil', true);
    }

    /**
     * @return BelongsTo<GaleriKegiatan, $this>
     */
    public function galeriKegiatan(): BelongsTo
    {
        return $this->belongsTo(GaleriKegiatan::class);
    }
}
