<?php

namespace App\Models;

use App\Enums\Semester;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Program Tahunan (PROTA) – yearly teaching programme for a subject in a class.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $mata_pelajaran_id
 * @property int $kelas_id
 * @property int $gtk_id
 * @property string|null $fase
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read MataPelajaran $mataPelajaran
 * @property-read Kelas $kelas
 * @property-read Gtk $gtk
 */
#[Fillable(['tahun_ajaran_id', 'mata_pelajaran_id', 'kelas_id', 'gtk_id', 'fase', 'catatan'])]
class ProgramTahunan extends Model implements ProvidesActivityTitle
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
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * @return BelongsTo<Gtk, $this>
     */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    /**
     * @return HasMany<ProgramTahunanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProgramTahunanItem::class)->orderBy('semester')->orderBy('urutan');
    }

    /**
     * Items for a given semester.
     *
     * @return HasMany<ProgramTahunanItem, $this>
     */
    public function itemsSemester(Semester $semester): HasMany
    {
        return $this->items()->where('semester', $semester->value);
    }

    public function totalJp(): int
    {
        return (int) $this->items()->sum('alokasi_waktu_jp');
    }

    public function totalMingguEfektif(): int
    {
        return (int) $this->items()->sum('minggu_efektif');
    }

    public function totalJpSemester(int $semester): int
    {
        return (int) $this->items()->where('semester', $semester)->sum('alokasi_waktu_jp');
    }

    public function totalMingguSemester(int $semester): int
    {
        return (int) $this->items()->where('semester', $semester)->sum('minggu_efektif');
    }

    public function activityTitle(): ?string
    {
        return "PROTA {$this->mataPelajaran->nama} {$this->kelas->nama} ({$this->tahunAjaran->nama})";
    }
}
