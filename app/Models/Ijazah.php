<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MemilikiBerkasArsip;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\IjazahFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One graduate's scanned diploma, grouped by graduation year. Linked to the student when the graduate is in the
 * Buku Induk; alumni from before the application are archived on their own.
 *
 * @property int $id
 * @property int|null $siswa_id
 * @property int $tahun_lulus
 * @property string|null $nama
 * @property string|null $nis
 * @property string|null $nisn
 * @property string|null $nomor_seri
 * @property Carbon|null $tanggal_ijazah
 * @property list<string>|null $berkas Paths on the archive disk, in page order.
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['siswa_id', 'tahun_lulus', 'nama', 'nis', 'nisn', 'nomor_seri', 'tanggal_ijazah', 'berkas', 'keterangan'])]
class Ijazah extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<IjazahFactory> */
    use HasFactory;

    use MemilikiBerkasArsip;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun_lulus' => 'integer',
            'tanggal_ijazah' => 'date',
            'berkas' => 'array',
        ];
    }

    /**
     * Link the diploma to the student with the same NIS or NISN, filling in what the archive row leaves blank.
     */
    protected static function booted(): void
    {
        static::saving(function (Ijazah $ijazah): void {
            if ($ijazah->siswa_id === null && (filled($ijazah->nis) || filled($ijazah->nisn))) {
                $ijazah->siswa_id = Siswa::withTrashed()
                    ->where(fn ($query) => $query
                        ->when(filled($ijazah->nis), fn ($query) => $query->orWhere('nis', $ijazah->nis))
                        ->when(filled($ijazah->nisn), fn ($query) => $query->orWhere('nisn', $ijazah->nisn)))
                    ->value('id');
            }

            if ($ijazah->siswa_id !== null && $ijazah->isDirty('siswa_id')) {
                $siswa = Siswa::withTrashed()->find($ijazah->siswa_id);

                $ijazah->nama = filled($ijazah->nama) ? $ijazah->nama : $siswa?->nama_lengkap;
                $ijazah->nis = filled($ijazah->nis) ? $ijazah->nis : $siswa?->nis;
                $ijazah->nisn = filled($ijazah->nisn) ? $ijazah->nisn : $siswa?->nisn;
            }
        });
    }

    /**
     * The student, even when moved to the trash.
     *
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    public function activityTitle(): ?string
    {
        return trim("{$this->tahun_lulus} - ".($this->nama ?? $this->nis ?? 'Tanpa nama'));
    }
}
