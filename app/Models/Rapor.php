<?php

namespace App\Models;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\RaporFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property string|null $wali_kelas
 * @property string|null $nip_wali_kelas
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

    use SoftDeletes;

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
     * Copy the class's homeroom teacher onto a report card that names none, so the printed report keeps the
     * teacher of that year even after the class gets a new one, and record the class in the student's class
     * history when that year has none yet.
     */
    protected static function booted(): void
    {
        static::saving(function (Rapor $rapor): void {
            if (blank($rapor->wali_kelas)) {
                $kelas = Kelas::query()->find($rapor->kelas_id);

                $rapor->wali_kelas = $kelas?->wali_kelas;
                $rapor->nip_wali_kelas = $kelas?->wali_kelas ? $kelas->nip_wali_kelas : $rapor->nip_wali_kelas;
            }
        });

        static::saved(function (Rapor $rapor): void {
            RiwayatKelas::query()->firstOrCreate(
                ['siswa_id' => $rapor->siswa_id, 'tahun_ajaran_id' => $rapor->tahun_ajaran_id],
                ['kelas_id' => $rapor->kelas_id],
            );
        });
    }

    /**
     * Who signs this report card as homeroom teacher: the name saved on the report card, or else the class's
     * current homeroom teacher (for report cards saved before a teacher was assigned).
     *
     * @return array{nama: string|null, nip: string|null}
     */
    public function penandaTanganWaliKelas(): array
    {
        if (filled($this->wali_kelas)) {
            return ['nama' => $this->wali_kelas, 'nip' => $this->nip_wali_kelas];
        }

        return ['nama' => $this->kelas?->wali_kelas, 'nip' => $this->kelas?->nip_wali_kelas];
    }

    /**
     * The student, even when moved to the trash, so a report card always knows whose it is.
     *
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /**
     * Report cards whose student is not in the trash.
     *
     * @param  Builder<Rapor>  $query
     */
    #[Scope]
    protected function milikSiswaAktif(Builder $query): void
    {
        $query->whereIn('siswa_id', Siswa::query()->select('id'));
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
