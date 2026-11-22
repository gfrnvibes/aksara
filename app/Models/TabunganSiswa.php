<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $siswa_id
 * @property int|null $pencatat_id
 * @property Carbon $tanggal
 * @property string $jenis
 * @property int $nominal
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Siswa $siswa
 * @property-read User|null $pencatat
 */
#[Fillable(['siswa_id', 'pencatat_id', 'tanggal', 'jenis', 'nominal', 'keterangan'])]
class TabunganSiswa extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nominal' => 'integer',
        ];
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    public static function saldoUntuk(int $siswaId): int
    {
        $setor = (int) static::query()->where('siswa_id', $siswaId)->where('jenis', 'setor')->sum('nominal');
        $tarik = (int) static::query()->where('siswa_id', $siswaId)->where('jenis', 'tarik')->sum('nominal');

        return $setor - $tarik;
    }
}
