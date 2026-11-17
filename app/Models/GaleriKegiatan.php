<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumentasi kegiatan siswa (foto & video) yang diunggah admin dan disimpan di R2.
 *
 * @property int $id
 * @property string $judul
 * @property string|null $kategori
 * @property Carbon|null $tanggal_kegiatan
 * @property string|null $deskripsi
 * @property array<int, string> $berkas
 * @property bool $is_publik
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['judul', 'kategori', 'tanggal_kegiatan', 'deskripsi', 'berkas', 'is_publik', 'user_id'])]
class GaleriKegiatan extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const array KATEGORI = [
        'Kesiswaan' => 'Kesiswaan',
        'Keagamaan' => 'Keagamaan',
        'Ekstrakurikuler' => 'Ekstrakurikuler',
        'Prestasi' => 'Prestasi',
        'Pembelajaran' => 'Pembelajaran',
        'Kegiatan Madrasah' => 'Kegiatan Madrasah',
        'Umum' => 'Umum',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'berkas' => 'array',
            'tanggal_kegiatan' => 'date',
            'is_publik' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $record): void {
            $lama = (array) ($record->getOriginal('berkas') ?? []);

            static::hapusBerkas(array_values(array_diff($lama, $record->berkas ?? [])));
        });

        static::deleted(fn (self $record) => static::hapusBerkas($record->berkas ?? []));
    }

    /**
     * Disk penyimpanan dokumentasi: Cloudflare R2 jika dikonfigurasi, atau arsip/local.
     */
    public static function disk(): string
    {
        if (filled(config('filesystems.disks.r2.bucket'))) {
            return 'r2';
        }

        return (string) config('filesystems.arsip', 'local');
    }

    public static function adalahVideo(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov', 'm4v', 'ogg'], true);
    }

    public function tautanMedia(string $path): string
    {
        $storage = Storage::disk(static::disk());

        return $storage->providesTemporaryUrls()
            ? $storage->temporaryUrl($path, now()->addHours(2))
            : $storage->url($path);
    }

    /**
     * Daftar media (foto & video) beserta URL bertanda tangan untuk ditampilkan.
     *
     * @return list<array{path: string, url: string, video: bool, nama: string}>
     */
    public function mediaUntukTampil(): array
    {
        return array_values(array_map(fn (string $path): array => [
            'path' => $path,
            'url' => $this->tautanMedia($path),
            'video' => static::adalahVideo($path),
            'nama' => basename($path),
        ], $this->berkas ?? []));
    }

    /**
     * @param  array<array-key, string>  $daftarBerkas
     */
    protected static function hapusBerkas(array $daftarBerkas): void
    {
        if ($daftarBerkas !== []) {
            Storage::disk(static::disk())->delete(array_values($daftarBerkas));
        }
    }

    /**
     * @param  Builder<GaleriKegiatan>  $query
     */
    #[Scope]
    protected function publik(Builder $query): void
    {
        $query->where('is_publik', true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<GaleriKomentar, $this>
     */
    public function komentar(): HasMany
    {
        return $this->hasMany(GaleriKomentar::class)->latest();
    }

    /**
     * @return HasMany<GaleriKomentar, $this>
     */
    public function komentarTampil(): HasMany
    {
        return $this->hasMany(GaleriKomentar::class)->where('is_tampil', true)->latest();
    }

    public function activityTitle(): ?string
    {
        return $this->judul;
    }
}
