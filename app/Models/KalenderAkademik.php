<?php

namespace App\Models;

use App\Enums\JenisAgenda;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\KalenderAkademikFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry of the academic calendar: a holiday, an exam week, a madrasah event or a meeting.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property string $nama
 * @property JenisAgenda $jenis
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 */
#[Fillable(['tahun_ajaran_id', 'nama', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'keterangan'])]
class KalenderAkademik extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<KalenderAkademikFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisAgenda::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
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
     * Entries still running or starting within the coming days.
     *
     * @param  Builder<KalenderAkademik>  $query
     */
    #[Scope]
    protected function mendatang(Builder $query, int $hari = 30): void
    {
        $query
            ->whereDate('tanggal_selesai', '>=', today())
            ->whereDate('tanggal_mulai', '<=', today()->addDays($hari))
            ->orderBy('tanggal_mulai');
    }

    /**
     * "17 Agustus 2026" or "22 – 26 Desember 2026".
     */
    public function rentang(): string
    {
        if ($this->tanggal_mulai->isSameDay($this->tanggal_selesai)) {
            return $this->tanggal_mulai->translatedFormat('j F Y');
        }

        $formatMulai = match (true) {
            $this->tanggal_mulai->isSameMonth($this->tanggal_selesai) => 'j',
            $this->tanggal_mulai->isSameYear($this->tanggal_selesai) => 'j F',
            default => 'j F Y',
        };

        return $this->tanggal_mulai->translatedFormat($formatMulai).' – '.$this->tanggal_selesai->translatedFormat('j F Y');
    }

    public function activityTitle(): ?string
    {
        return "{$this->nama} ({$this->rentang()})";
    }
}
