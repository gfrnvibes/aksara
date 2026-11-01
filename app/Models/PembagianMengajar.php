<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\PembagianMengajarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who teaches a subject in a class during an academic year, and for how many lessons (JP) a week. Each subject of
 * a class has one teacher per year; the assignment decides which grades the teacher may enter.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $kelas_id
 * @property int $mata_pelajaran_id
 * @property int $gtk_id
 * @property int $jp_per_minggu
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Kelas $kelas
 * @property-read MataPelajaran $mataPelajaran
 * @property-read Gtk $gtk
 */
#[Fillable(['tahun_ajaran_id', 'kelas_id', 'mata_pelajaran_id', 'gtk_id', 'jp_per_minggu'])]
class PembagianMengajar extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<PembagianMengajarFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jp_per_minggu' => 'integer',
        ];
    }

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
     * The teacher's assignments, in every academic year unless one is given.
     *
     * @param  Builder<PembagianMengajar>  $query
     */
    #[Scope]
    protected function milik(Builder $query, ?Gtk $gtk, ?int $tahunAjaranId = null): void
    {
        $query
            ->where($query->qualifyColumn('gtk_id'), $gtk->id ?? 0)
            ->when($tahunAjaranId, fn (Builder $query, int $id) => $query->where($query->qualifyColumn('tahun_ajaran_id'), $id));
    }

    public function activityTitle(): ?string
    {
        return "{$this->mataPelajaran->nama} {$this->kelas->nama} ({$this->tahunAjaran->nama})";
    }
}
