<?php

namespace App\Models;

use App\Enums\Kurikulum;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use App\Services\Kurikulum\SkemaPenilaian;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Carbon\CarbonInterface;
use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $ppdb_dibuka First day the public PPDB form takes registrations for this year.
 * @property Carbon|null $ppdb_ditutup Last day of registration; open-ended when empty.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'kurikulum', 'is_aktif', 'kepala_madrasah', 'nip_kepala_madrasah', 'ppdb_dibuka', 'ppdb_ditutup'])]
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
            'ppdb_dibuka' => 'date',
            'ppdb_ditutup' => 'date',
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
     * The academic year a date falls in (July–June), or the active year when that one is not recorded.
     */
    public static function untukTanggal(CarbonInterface $tanggal): ?self
    {
        $tahunMulai = $tanggal->month >= 7 ? $tanggal->year : $tanggal->year - 1;

        return static::query()->where('nama', $tahunMulai.'/'.($tahunMulai + 1))->first()
            ?? static::query()->where('is_aktif', true)->first();
    }

    /**
     * The academic year whose PPDB registration is open today, if any.
     */
    public static function pendaftaranPpdbDibuka(): ?self
    {
        $hariIni = today();

        return static::query()
            ->whereDate('ppdb_dibuka', '<=', $hariIni)
            ->where(fn (Builder $query) => $query->whereNull('ppdb_ditutup')->orWhereDate('ppdb_ditutup', '>=', $hariIni))
            ->orderByDesc('nama')
            ->first();
    }

    /**
     * The academic year a new registration belongs to: the one open today, else the latest year given a PPDB
     * period, else the active year.
     */
    public static function untukPpdb(): ?self
    {
        return static::pendaftaranPpdbDibuka()
            ?? static::query()->whereNotNull('ppdb_dibuka')->orderByDesc('nama')->first()
            ?? static::query()->where('is_aktif', true)->first();
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

    /**
     * @return HasMany<Ppdb, $this>
     */
    public function ppdb(): HasMany
    {
        return $this->hasMany(Ppdb::class);
    }

    public function keteranganPemakaian(): ?string
    {
        $jumlahRapor = $this->rapor()->withTrashed()->count();
        $jumlahRiwayat = $this->riwayatKelas()->count();
        $jumlahPendaftar = $this->ppdb()->count();

        return $jumlahRapor === 0 && $jumlahRiwayat === 0 && $jumlahPendaftar === 0
            ? null
            : "Tahun ajaran {$this->nama} masih dipakai oleh {$jumlahRapor} rapor, {$jumlahRiwayat} riwayat kelas, dan {$jumlahPendaftar} pendaftar PPDB.";
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
