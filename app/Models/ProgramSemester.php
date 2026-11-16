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
 * Program Semester (PROSEM / PROMES) Kurikulum Merdeka.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $semester
 * @property int $kelas_id
 * @property int $mata_pelajaran_id
 * @property int $gtk_id
 * @property int|null $program_tahunan_id
 * @property string $fase
 * @property int $jp_per_minggu
 * @property int $total_minggu_efektif
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Kelas $kelas
 * @property-read MataPelajaran $mataPelajaran
 * @property-read Gtk $gtk
 * @property-read ProgramTahunan|null $programTahunan
 */
#[Fillable([
    'tahun_ajaran_id', 'semester', 'kelas_id', 'mata_pelajaran_id', 'gtk_id',
    'program_tahunan_id', 'fase', 'jp_per_minggu', 'total_minggu_efektif', 'catatan',
])]
class ProgramSemester extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'jp_per_minggu' => 'integer',
            'total_minggu_efektif' => 'integer',
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
     * @return BelongsTo<ProgramTahunan, $this>
     */
    public function programTahunan(): BelongsTo
    {
        return $this->belongsTo(ProgramTahunan::class);
    }

    /**
     * @return HasMany<ProgramSemesterItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProgramSemesterItem::class)->orderBy('urutan');
    }

    public function totalJp(): int
    {
        return (int) $this->items()->sum('alokasi_waktu_jp');
    }

    /**
     * List of months covered in this semester.
     *
     * @return list<string>
     */
    public function daftarBulan(): array
    {
        return $this->semester === Semester::Genap->value
            ? ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni']
            : ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }

    public function labelSemester(): string
    {
        return $this->semester === Semester::Genap->value
            ? 'Semester 2 (Genap)'
            : 'Semester 1 (Ganjil)';
    }

    public function activityTitle(): ?string
    {
        $sem = $this->semester === Semester::Genap->value ? 'Genap' : 'Ganjil';

        return "PROSEM {$this->mataPelajaran->nama} {$this->kelas->nama} ({$this->tahunAjaran->nama} Sem {$sem})";
    }
}
