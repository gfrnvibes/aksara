<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Alur Tujuan Pembelajaran (ATP) Kurikulum Merdeka.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property string $jenjang
 * @property int $kelas_id
 * @property int $mata_pelajaran_id
 * @property int $gtk_id
 * @property string $fase
 * @property string|null $capaian_pembelajaran_fase
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Kelas $kelas
 * @property-read MataPelajaran $mataPelajaran
 * @property-read Gtk $gtk
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AlurTujuanPembelajaranItem> $items
 */
#[Fillable([
    'tahun_ajaran_id', 'jenjang', 'kelas_id', 'mata_pelajaran_id', 'gtk_id',
    'fase', 'capaian_pembelajaran_fase', 'catatan',
])]
class AlurTujuanPembelajaran extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * @return BelongsTo<Gtk, $this>
     */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    /**
     * @return HasMany<AlurTujuanPembelajaranItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AlurTujuanPembelajaranItem::class)->orderBy('urutan');
    }

    public function totalJp(): int
    {
        return (int) $this->items()->sum('alokasi_waktu_jp');
    }

    public function jumlahTp(): int
    {
        return (int) $this->items()->count();
    }

    public function activityTitle(): ?string
    {
        return "ATP {$this->mataPelajaran->nama} {$this->kelas->nama} ({$this->fase})";
    }
}
