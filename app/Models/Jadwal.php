<?php

namespace App\Models;

use App\Enums\Semester;
use App\Enums\StatusJadwal;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\JadwalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A weekly lesson timetable for one semester. Several drafts may be kept; one is published, and that is the one
 * teachers see.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property Semester $semester
 * @property string|null $nama
 * @property StatusJadwal $status
 * @property Carbon|null $diterbitkan_pada
 * @property list<array{tingkat: string, pesan: string}>|null $laporan What the last automatic build or check found.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 */
#[Fillable(['tahun_ajaran_id', 'semester', 'nama', 'status', 'diterbitkan_pada', 'laporan'])]
class Jadwal extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<JadwalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'status' => StatusJadwal::class,
            'diterbitkan_pada' => 'datetime',
            'laporan' => 'array',
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
     * @return HasMany<IsiJadwal, $this>
     */
    public function isi(): HasMany
    {
        return $this->hasMany(IsiJadwal::class);
    }

    /**
     * The published timetable of the semester, if any.
     */
    public static function terbitUntuk(?int $tahunAjaranId, Semester $semester): ?self
    {
        return static::query()
            ->where('tahun_ajaran_id', $tahunAjaranId ?? 0)
            ->where('semester', $semester)
            ->where('status', StatusJadwal::Terbit)
            ->first();
    }

    /**
     * Publish this timetable, returning any other timetable of the semester to draft.
     */
    public function terbitkan(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('tahun_ajaran_id', $this->tahun_ajaran_id)
                ->where('semester', $this->semester)
                ->whereKeyNot($this->getKey())
                ->where('status', StatusJadwal::Terbit)
                ->get()
                ->each(fn (Jadwal $jadwal) => $jadwal->update(['status' => StatusJadwal::Draf, 'diterbitkan_pada' => null]));

            $this->update(['status' => StatusJadwal::Terbit, 'diterbitkan_pada' => now()]);
        });
    }

    /**
     * "Semester Ganjil 2026/2027".
     */
    public function judul(): string
    {
        $semester = $this->semester === Semester::Ganjil ? 'Ganjil' : 'Genap';

        return "Semester {$semester} {$this->tahunAjaran->nama}".($this->nama ? " — {$this->nama}" : '');
    }

    public function activityTitle(): ?string
    {
        return "Jadwal {$this->judul()}";
    }
}
