<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $gtk_id
 * @property string $periode
 * @property string $nama_guru
 * @property string|null $nip
 * @property int $gaji_pokok
 * @property int $tunjangan
 * @property int $potongan
 * @property string|null $catatan
 * @property-read Gtk $gtk
 * @property-read User $pencatat
 */
#[Guarded(['id'])]
class GajiGuru extends Model
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return ['gaji_pokok' => 'integer', 'tunjangan' => 'integer', 'potongan' => 'integer'];
    }

    /** @return BelongsTo<Gtk, $this> */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    /** @return HasMany<TransaksiKeuangan, $this> */
    public function transaksi(): HasMany
    {
        return $this->hasMany(TransaksiKeuangan::class);
    }

    /** @return HasMany<TransaksiKeuangan, $this> */
    public function pembayaran(): HasMany
    {
        return $this->transaksi()->whereNull('dibatalkan_at');
    }

    public function bersih(): int
    {
        return $this->gaji_pokok + $this->tunjangan - $this->potongan;
    }

    public function sudahDibayar(): bool
    {
        return $this->getAttribute('pembayaran_exists') ?? $this->pembayaran()->exists();
    }
}
