<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nomor
 * @property int|null $gaji_guru_id
 * @property int|null $tagihan_siswa_id
 * @property Carbon $tanggal
 * @property string $jenis
 * @property string $kategori
 * @property string $uraian
 * @property int $nominal
 * @property string $metode
 * @property string|null $referensi
 * @property Carbon|null $dibatalkan_at
 * @property string|null $alasan_pembatalan
 * @property-read TagihanSiswa|null $tagihanSiswa
 * @property-read User $pencatat
 * @property-read User|null $pembatal
 */
#[Guarded(['id'])]
class TransaksiKeuangan extends Model
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return ['nominal' => 'integer', 'tanggal' => 'date', 'dibatalkan_at' => 'datetime'];
    }

    /** @return BelongsTo<TagihanSiswa, $this> */
    public function tagihanSiswa(): BelongsTo
    {
        return $this->belongsTo(TagihanSiswa::class);
    }

    /** @return BelongsTo<GajiGuru, $this> */
    public function gajiGuru(): BelongsTo
    {
        return $this->belongsTo(GajiGuru::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembatal_id');
    }
}
