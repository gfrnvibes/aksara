<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An exam room belonging to a {@see KegiatanUjian}, with desk column count, seats per desk (1 or 2), and capacity.
 *
 * @property int $id
 * @property int $kegiatan_ujian_id
 * @property string $kode
 * @property string $nama
 * @property int $urutan
 * @property int $jumlah_kolom
 * @property int $kursi_per_meja
 * @property int $kapasitas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KegiatanUjian $kegiatanUjian
 * @property-read Collection<int, PenempatanUjian> $penempatan
 */
#[Fillable(['kegiatan_ujian_id', 'kode', 'nama', 'urutan', 'jumlah_kolom', 'kursi_per_meja', 'kapasitas'])]
class RuangUjian extends Model
{
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'jumlah_kolom' => 'integer',
            'kursi_per_meja' => 'integer',
            'kapasitas' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<KegiatanUjian, $this>
     */
    public function kegiatanUjian(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjian::class);
    }

    /**
     * @return HasMany<PenempatanUjian, $this>
     */
    public function penempatan(): HasMany
    {
        return $this->hasMany(PenempatanUjian::class)->orderBy('urutan_posisi')->orderBy('nomor_kursi');
    }
}
