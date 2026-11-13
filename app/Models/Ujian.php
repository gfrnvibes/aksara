<?php

namespace App\Models;

use App\Enums\Semester;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\UjianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A paper exam put together from the question bank, printed in one or more packets (A, B, …) whose questions
 * and choices come in a different order.
 *
 * @property int $id
 * @property string $nama
 * @property int $tahun_ajaran_id
 * @property Semester $semester
 * @property int $mata_pelajaran_id
 * @property int $tingkat
 * @property Carbon|null $tanggal
 * @property int $durasi_menit
 * @property int $jumlah_paket
 * @property string|null $petunjuk
 * @property int|null $user_id The author.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read MataPelajaran $mataPelajaran
 * @property-read Collection<int, ButirUjian> $butir
 */
#[Fillable(['nama', 'tahun_ajaran_id', 'semester', 'mata_pelajaran_id', 'tingkat', 'tanggal', 'durasi_menit', 'jumlah_paket', 'petunjuk', 'user_id'])]
class Ujian extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<UjianFactory> */
    use HasFactory;

    public const int PAKET_MAKS = 4;

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'tingkat' => 'integer',
            'tanggal' => 'date',
            'durasi_menit' => 'integer',
            'jumlah_paket' => 'integer',
        ];
    }

    /**
     * @return list<string> "A", "B", …
     */
    public function daftarPaket(): array
    {
        return array_slice(range('A', 'Z'), 0, max(1, min(static::PAKET_MAKS, $this->jumlah_paket)));
    }

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
     * @return HasMany<ButirUjian, $this>
     */
    public function butir(): HasMany
    {
        return $this->hasMany(ButirUjian::class)->orderBy('urutan');
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
