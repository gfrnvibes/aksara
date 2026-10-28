<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\IjazahFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Minutes a preview or download link stays valid.
     */
    public const int MASA_BERLAKU_TAUTAN = 10;

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
     * Link the diploma to the student with the same NIS or NISN, filling in what the archive row leaves blank, and
     * remove files from storage once they are taken off the record.
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

        static::updated(function (Ijazah $ijazah): void {
            $lama = (array) ($ijazah->getOriginal('berkas') ?? []);

            static::hapusBerkas(array_values(array_diff($lama, $ijazah->berkas ?? [])));
        });

        static::deleted(fn (Ijazah $ijazah) => static::hapusBerkas($ijazah->berkas ?? []));
    }

    /**
     * The disk scanned documents are kept on (Cloudflare R2 in production).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.arsip', 'local');
    }

    /**
     * A short-lived link to one of this diploma's files, so the file goes straight from storage to the browser.
     */
    public function tautanSementara(string $path): string
    {
        return Storage::disk(static::disk())->temporaryUrl($path, now()->addMinutes(static::MASA_BERLAKU_TAUTAN));
    }

    public static function adalahPdf(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * @param  list<string>  $daftarBerkas
     */
    protected static function hapusBerkas(array $daftarBerkas): void
    {
        if ($daftarBerkas !== []) {
            Storage::disk(static::disk())->delete($daftarBerkas);
        }
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
