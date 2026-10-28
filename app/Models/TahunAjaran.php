<?php

namespace App\Models;

use App\Enums\Kurikulum;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use App\Services\Kurikulum\SkemaPenilaian;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property Kurikulum $kurikulum
 * @property bool $is_aktif
 * @property string|null $kepala_madrasah
 * @property string|null $nip_kepala_madrasah
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'kurikulum', 'is_aktif', 'kepala_madrasah', 'nip_kepala_madrasah'])]
class TahunAjaran extends Model implements ProvidesActivityTitle, TerlindungDariHapus
{
    use CatatAktivitas;

    /** @use HasFactory<TahunAjaranFactory> */
    use HasFactory;

    use MenolakHapusSaatDipakai;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kurikulum' => Kurikulum::class,
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * Ensure only one academic year is marked as active.
     */
    protected static function booted(): void
    {
        static::saved(function (TahunAjaran $tahunAjaran): void {
            if ($tahunAjaran->is_aktif) {
                static::query()
                    ->whereKeyNot($tahunAjaran->getKey())
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }
        });
    }

    /**
     * The grading scheme of the curriculum this year follows.
     */
    public function skema(): SkemaPenilaian
    {
        return $this->kurikulum->skema();
    }

    /**
     * The grading scheme of an academic year, or of the current curriculum when no year is chosen yet.
     */
    public static function skemaUntuk(?int $tahunAjaranId): SkemaPenilaian
    {
        $kurikulum = $tahunAjaranId === null ? null : static::query()->whereKey($tahunAjaranId)->value('kurikulum');

        return ($kurikulum instanceof Kurikulum ? $kurikulum : Kurikulum::tryFrom((string) $kurikulum) ?? Kurikulum::bawaan())->skema();
    }

    /**
     * @return HasMany<Rapor, $this>
     */
    public function rapor(): HasMany
    {
        return $this->hasMany(Rapor::class);
    }

    /**
     * @return HasMany<RiwayatKelas, $this>
     */
    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class);
    }

    public function keteranganPemakaian(): ?string
    {
        $jumlahRapor = $this->rapor()->withTrashed()->count();
        $jumlahRiwayat = $this->riwayatKelas()->count();

        return $jumlahRapor === 0 && $jumlahRiwayat === 0
            ? null
            : "Tahun ajaran {$this->nama} masih dipakai oleh {$jumlahRapor} rapor dan {$jumlahRiwayat} riwayat kelas.";
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
