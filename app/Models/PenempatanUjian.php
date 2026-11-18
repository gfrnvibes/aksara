<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's exam card number and seat assignment in a {@see KegiatanUjian}.
 *
 * @property int $id
 * @property int $kegiatan_ujian_id
 * @property int|null $ruang_ujian_id
 * @property int $siswa_id
 * @property int|null $kelas_id
 * @property string $nomor_peserta
 * @property int|null $nomor_kursi
 * @property int|null $urutan_posisi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KegiatanUjian $kegiatanUjian
 * @property-read RuangUjian|null $ruangUjian
 * @property-read Siswa $siswa
 * @property-read Kelas|null $kelas
 */
#[Fillable([
    'kegiatan_ujian_id',
    'ruang_ujian_id',
    'siswa_id',
    'kelas_id',
    'nomor_peserta',
    'nomor_kursi',
    'urutan_posisi',
])]
class PenempatanUjian extends Model
{
    protected function casts(): array
    {
        return [
            'nomor_kursi' => 'integer',
            'urutan_posisi' => 'integer',
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
     * @return BelongsTo<RuangUjian, $this>
     */
    public function ruangUjian(): BelongsTo
    {
        return $this->belongsTo(RuangUjian::class);
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }
}
