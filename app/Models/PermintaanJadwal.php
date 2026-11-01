<?php

namespace App\Models;

use App\Enums\Hari;
use App\Enums\JenisPermintaanJadwal;
use App\Enums\SifatPermintaanJadwal;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\PermintaanJadwalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A teacher's wish for the timetable: times they cannot teach, or the only times they want to teach, on some
 * days and lesson periods, optionally for one subject only. A required wish is never broken by the automatic
 * timetable; the others are followed when possible.
 *
 * @property int $id
 * @property int $gtk_id
 * @property JenisPermintaanJadwal $jenis
 * @property SifatPermintaanJadwal $sifat
 * @property list<int>|null $hari Days it covers (ISO weekday numbers); every school day when empty.
 * @property int|null $jp_mulai First lesson period it covers; from the first period when empty.
 * @property int|null $jp_selesai Last lesson period it covers; to the last period when empty.
 * @property int|null $mata_pelajaran_id The only subject it applies to; all of the teacher's subjects when empty.
 * @property string|null $keterangan
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gtk $gtk
 * @property-read MataPelajaran|null $mataPelajaran
 */
#[Fillable(['gtk_id', 'jenis', 'sifat', 'hari', 'jp_mulai', 'jp_selesai', 'mata_pelajaran_id', 'keterangan', 'is_aktif'])]
class PermintaanJadwal extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<PermintaanJadwalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisPermintaanJadwal::class,
            'sifat' => SifatPermintaanJadwal::class,
            'hari' => 'array',
            'jp_mulai' => 'integer',
            'jp_selesai' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Gtk, $this>
     */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * Whether the wish is about this subject.
     */
    public function berlakuUntukMapel(int $mataPelajaranId): bool
    {
        return $this->mata_pelajaran_id === null || $this->mata_pelajaran_id === $mataPelajaranId;
    }

    /**
     * Whether the lesson period falls within the days and periods the wish names.
     */
    public function mencakup(Hari $hari, int $jpKe): bool
    {
        return (blank($this->hari) || in_array($hari->value, array_map(intval(...), $this->hari), true))
            && ($this->jp_mulai === null || $jpKe >= $this->jp_mulai)
            && ($this->jp_selesai === null || $jpKe <= $this->jp_selesai);
    }

    /**
     * Whether teaching the subject at this lesson period goes against the wish.
     */
    public function dilanggarOleh(Hari $hari, int $jpKe, int $mataPelajaranId): bool
    {
        if (! $this->berlakuUntukMapel($mataPelajaranId)) {
            return false;
        }

        return $this->jenis === JenisPermintaanJadwal::TidakBisa
            ? $this->mencakup($hari, $jpKe)
            : ! $this->mencakup($hari, $jpKe);
    }

    /**
     * "Tidak bisa mengajar pada Jum'at, Sabtu, JP 1–4 (Matematika)".
     */
    public function ringkasan(): string
    {
        $hari = blank($this->hari)
            ? 'semua hari'
            : collect($this->hari)->map(fn (mixed $hari): string => Hari::from((int) $hari)->getLabel())->join(', ');

        $jp = match (true) {
            $this->jp_mulai === null && $this->jp_selesai === null => 'seharian',
            $this->jp_mulai === $this->jp_selesai => "JP {$this->jp_mulai}",
            default => 'JP '.($this->jp_mulai ?? 1).'–'.($this->jp_selesai ?? 'terakhir'),
        };

        return "{$this->jenis->getLabel()} {$hari}, {$jp}".($this->mataPelajaran ? " ({$this->mataPelajaran->nama})" : '');
    }

    public function activityTitle(): ?string
    {
        return "{$this->gtk->nama}: {$this->ringkasan()}";
    }
}
