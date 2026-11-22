<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $judul
 * @property string|null $penulis
 * @property string|null $penerbit
 * @property int|null $tahun_terbit
 * @property string|null $isbn
 * @property string $kategori
 * @property string $sumber
 * @property int|null $tingkat
 * @property int|null $mata_pelajaran_id
 * @property string|null $deskripsi
 * @property string|null $sampul
 * @property string $file_pdf
 * @property string|null $sumber_url
 * @property int $jumlah_dibaca
 * @property bool $is_aktif
 * @property int|null $pengunggah_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MataPelajaran|null $mataPelajaran
 * @property-read User|null $pengunggah
 */
#[Fillable([
    'judul',
    'penulis',
    'penerbit',
    'tahun_terbit',
    'isbn',
    'kategori',
    'sumber',
    'tingkat',
    'mata_pelajaran_id',
    'deskripsi',
    'sampul',
    'file_pdf',
    'sumber_url',
    'jumlah_dibaca',
    'is_aktif',
    'pengunggah_id',
])]
class BukuPerpustakaan extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @var array<string, string> */
    public const array KATEGORI = [
        'paket' => 'Buku Paket',
        'pai' => 'PAI & Bahasa Arab',
        'literasi' => 'Literasi & Bacaan',
        'referensi' => 'Referensi & Modul',
    ];

    /** @var array<string, string> */
    public const array SUMBER = [
        'sibi' => 'SIBI Kemendikbud',
        'kemenag' => 'Kemenag (PAI & B. Arab)',
        'manual' => 'Modul / Internal Madrasah',
    ];

    /** @var array<int, string> */
    public const array TINGKAT = [
        7 => 'Kelas VII',
        8 => 'Kelas VIII',
        9 => 'Kelas IX',
    ];

    protected function casts(): array
    {
        return [
            'tahun_terbit' => 'integer',
            'tingkat' => 'integer',
            'jumlah_dibaca' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<MataPelajaran, $this> */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengunggah_id');
    }

    /** @param Builder<BukuPerpustakaan> $query */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    public function activityTitle(): string
    {
        return $this->judul;
    }

    public function sampulUrl(): ?string
    {
        if (blank($this->sampul)) {
            return null;
        }

        if (str_starts_with($this->sampul, 'http://') || str_starts_with($this->sampul, 'https://')) {
            return $this->sampul;
        }

        if (! Storage::disk('public')->exists($this->sampul)) {
            return null;
        }

        return route('perpustakaan.sampul', $this);
    }
}
