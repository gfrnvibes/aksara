<?php

namespace App\Models;

use App\Enums\JenisSoal;
use App\Enums\LevelKognitif;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\SoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One question in the shared question bank of a subject and grade. Teachers of the subject all see it; only its
 * author and the curriculum staff change it.
 *
 * `opsi` holds the rows the kind needs, in order:
 * - PG / PG Kompleks: {teks, gambar, benar}, one (PG) or more (kompleks) marked benar;
 * - Benar/Salah: {teks, benar}, one statement each;
 * - Menjodohkan: {teks, pasangan}, the pair as answered; the printed paper shuffles the right column.
 *
 * @property int $id
 * @property int $mata_pelajaran_id
 * @property int $tingkat
 * @property int|null $user_id The author.
 * @property JenisSoal $jenis
 * @property string $materi
 * @property string|null $indikator
 * @property LevelKognitif|null $level_kognitif
 * @property string $pertanyaan HTML from the rich editor.
 * @property string|null $gambar
 * @property array<array-key, array{teks?: string|null, gambar?: string|null, benar?: bool, pasangan?: string|null}>|null $opsi Keyed by the form until saved, a list after.
 * @property string|null $pembahasan The scoring guide of an essay, or an explanation of the answer.
 * @property bool $is_siap A draft (for example from Gemini) is hidden from exams until a teacher checks it.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MataPelajaran $mataPelajaran
 * @property-read User|null $pembuat
 */
#[Fillable([
    'mata_pelajaran_id', 'tingkat', 'user_id', 'jenis', 'materi', 'indikator', 'level_kognitif', 'pertanyaan', 'gambar',
    'opsi', 'pembahasan', 'is_siap',
])]
class Soal extends Model implements ProvidesActivityTitle, TerlindungDariHapus
{
    use CatatAktivitas;

    /** @use HasFactory<SoalFactory> */
    use HasFactory;

    use MenolakHapusSaatDipakai;

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'jenis' => JenisSoal::class,
            'level_kognitif' => LevelKognitif::class,
            'opsi' => 'array',
            'is_siap' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Soal $soal): void {
            $soal->opsi = $soal->jenis === JenisSoal::Esai ? null : array_values($soal->opsi ?? []);
        });

        static::updated(function (Soal $soal): void {
            $lama = static::daftarGambar($soal->getOriginal('gambar'), (array) json_decode((string) $soal->getRawOriginal('opsi'), true));
            static::hapusGambar(array_diff($lama, static::daftarGambar($soal->gambar, $soal->opsi ?? [])));
        });

        static::deleted(fn (Soal $soal) => static::hapusGambar(static::daftarGambar($soal->gambar, $soal->opsi ?? [])));
    }

    /**
     * The disk question images live on: CBT_DISK, else the archive disk (Cloudflare R2 in production).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.cbt', 'local');
    }

    /**
     * Why the answer key of the rows does not fit the kind, or null when it does.
     *
     * @param  array<array-key, mixed>  $opsi
     */
    public static function kesalahanKunci(JenisSoal $jenis, array $opsi): ?string
    {
        [$min, $maks] = $jenis->batasOpsi();
        $jumlah = count($opsi);

        if ($jenis !== JenisSoal::Esai && ($jumlah < $min || $jumlah > $maks)) {
            return "{$jenis->getLabel()} memerlukan {$min} sampai {$maks} {$jenis->labelOpsi()}.";
        }

        $benar = count(array_filter($opsi, fn (mixed $baris): bool => is_array($baris) && (bool) ($baris['benar'] ?? false)));

        return match ($jenis) {
            JenisSoal::PilihanGanda => $benar === 1 ? null : 'Tandai tepat satu pilihan sebagai jawaban benar.',
            JenisSoal::PgKompleks => $benar >= 1 && $benar < $jumlah ? null : 'Tandai minimal satu pilihan benar dan sisakan minimal satu pilihan salah.',
            default => null,
        };
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ButirUjian, $this>
     */
    public function butirUjian(): HasMany
    {
        return $this->hasMany(ButirUjian::class);
    }

    public function keteranganPemakaian(): ?string
    {
        $jumlah = $this->butirUjian()->count();

        return $jumlah === 0 ? null : "Soal ini dipakai di {$jumlah} ujian. Keluarkan dari ujian tersebut sebelum menghapusnya.";
    }

    /**
     * A link the browser can load an image from: a short-lived one on a private disk (R2), else the public URL.
     */
    public static function urlGambar(string $path): string
    {
        $disk = Storage::disk(static::disk());

        return $disk->providesTemporaryUrls() ? $disk->temporaryUrl($path, now()->addHours(2)) : $disk->url($path);
    }

    public function activityTitle(): ?string
    {
        return str((string) strip_tags($this->pertanyaan))->limit(60)->toString();
    }

    /**
     * @param  array<array-key, mixed>  $opsi
     * @return list<string>
     */
    protected static function daftarGambar(?string $gambar, array $opsi): array
    {
        $daftar = array_map(fn (mixed $baris): mixed => is_array($baris) ? ($baris['gambar'] ?? null) : null, $opsi);

        return array_values(array_filter([$gambar, ...$daftar], is_string(...)));
    }

    /**
     * @param  array<array-key, string>  $daftar
     */
    protected static function hapusGambar(array $daftar): void
    {
        if ($daftar !== []) {
            Storage::disk(static::disk())->delete(array_values($daftar));
        }
    }
}
