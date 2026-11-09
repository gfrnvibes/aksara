<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $siswa_id
 * @property int $tahun_ajaran_id
 * @property string $nama
 * @property string $periode
 * @property int $nominal
 * @property Carbon $jatuh_tempo
 * @property Carbon|null $dibatalkan_at
 * @property-read Siswa $siswa
 * @property-read TahunAjaran $tahunAjaran
 */
#[Guarded(['id'])]
class TagihanSiswa extends Model
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return ['nominal' => 'integer', 'jatuh_tempo' => 'date', 'dibatalkan_at' => 'datetime'];
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /** @return HasMany<TransaksiKeuangan, $this> */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(TransaksiKeuangan::class)->whereNull('dibatalkan_at');
    }

    public function terbayar(): int
    {
        return (int) ($this->getAttribute('pembayaran_sum_nominal') ?? $this->pembayaran()->sum('nominal'));
    }

    public function sisa(): int
    {
        return max(0, $this->nominal - $this->terbayar());
    }

    public function status(): string
    {
        return match (true) {
            $this->dibatalkan_at !== null => 'Dibatalkan',
            $this->sisa() === 0 => 'Lunas',
            $this->jatuh_tempo->lt(today()) => 'Menunggak',
            $this->terbayar() > 0 => 'Dicicil',
            default => 'Belum dibayar',
        };
    }
}
