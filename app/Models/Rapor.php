<?php

namespace App\Models;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\RaporFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $siswa_id
 * @property int $tahun_ajaran_id
 * @property int $kelas_id
 * @property Semester $semester
 * @property int $sakit
 * @property int $izin
 * @property int $tanpa_keterangan
 * @property string|null $nilai_akhlak
 * @property string|null $nilai_kepribadian
 * @property array<int, array{prestasi: string|null, nilai: string|null, keterangan: string|null}>|null $prestasi
 * @property string|null $catatan
 * @property KeputusanKenaikan|null $keputusan
 * @property Carbon|null $tanggal_keputusan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id'])]
class Rapor extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<RaporFactory> */
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
            'keputusan' => KeputusanKenaikan::class,
            'tanggal_keputusan' => 'date',
            'prestasi' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
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
     * @return HasMany<NilaiRapor, $this>
     */
    public function nilaiRapor(): HasMany
    {
        return $this->hasMany(NilaiRapor::class);
    }

    /**
     * @return HasMany<NilaiEkstrakurikuler, $this>
     */
    public function nilaiEkstrakurikuler(): HasMany
    {
        return $this->hasMany(NilaiEkstrakurikuler::class);
    }

    public function activityTitle(): ?string
    {
        return "{$this->siswa->nama_lengkap} - {$this->tahunAjaran->nama} {$this->semester->getLabel()}";
    }
}
