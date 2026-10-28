<?php

namespace App\Models;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\JenisOrangTua;
use App\Enums\StatusSiswa;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\SiswaFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nis
 * @property string|null $nisn
 * @property int|null $kelas_id
 * @property StatusSiswa $status
 * @property string|null $foto
 * @property string $nama_lengkap
 * @property string|null $nama_panggilan
 * @property JenisKelamin $jenis_kelamin
 * @property string|null $tempat_lahir
 * @property Carbon|null $tanggal_lahir
 * @property Agama|null $agama
 * @property string $kewarganegaraan
 * @property array<int, array{tahun: string|null, kelas: string|null, dari: string|null}>|null $beasiswa
 * @property array<int, string>|null $prestasi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id'])]
class Siswa extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<SiswaFactory> */
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
            'status' => StatusSiswa::class,
            'jenis_kelamin' => JenisKelamin::class,
            'agama' => Agama::class,
            'tanggal_lahir' => 'date',
            'tanggal_sttb' => 'date',
            'tanggal_diterima' => 'date',
            'tanggal_meninggalkan_sekolah' => 'date',
            'tanggal_tamat' => 'date',
            'tanggal_mulai_bekerja' => 'date',
            'beasiswa' => 'array',
            'prestasi' => 'array',
        ];
    }

    /**
     * Record the new current class in the class history whenever it is set or changed.
     */
    protected static function booted(): void
    {
        static::saved(function (Siswa $siswa): void {
            if ($siswa->kelas_id !== null && ($siswa->wasRecentlyCreated || $siswa->wasChanged('kelas_id'))) {
                $siswa->catatKelasSaatIni();
            }
        });
    }

    /**
     * Write the current class onto the latest academic year of the class history: the active year, or a
     * later year the student was already promoted into.
     */
    public function catatKelasSaatIni(): void
    {
        $tahunAktif = TahunAjaran::query()->where('is_aktif', true)->first();

        $riwayatTerakhir = $this->riwayatKelas()
            ->join('tahun_ajarans', 'tahun_ajarans.id', '=', 'riwayat_kelas.tahun_ajaran_id')
            ->orderByDesc('tahun_ajarans.nama')
            ->select('riwayat_kelas.*', 'tahun_ajarans.nama as nama_tahun_ajaran')
            ->first();

        if ($riwayatTerakhir && ($tahunAktif === null || strcmp($riwayatTerakhir->getAttribute('nama_tahun_ajaran'), $tahunAktif->nama) >= 0)) {
            RiwayatKelas::query()->find($riwayatTerakhir->id)?->update(['kelas_id' => $this->kelas_id]);

            return;
        }

        if ($tahunAktif) {
            RiwayatKelas::query()->updateOrCreate(
                ['siswa_id' => $this->id, 'tahun_ajaran_id' => $tahunAktif->id],
                ['kelas_id' => $this->kelas_id],
            );
        }
    }

    /**
     * The class the student sat in during the given academic year, falling back to the current class.
     */
    public function kelasPadaTahunAjaran(?int $tahunAjaranId): ?int
    {
        if ($tahunAjaranId === null) {
            return $this->kelas_id;
        }

        return $this->riwayatKelas()->where('tahun_ajaran_id', $tahunAjaranId)->value('kelas_id') ?? $this->kelas_id;
    }

    /**
     * Students who sat in the class during the academic year: from the class history, plus — for the active
     * year — students currently in the class whose history has no row for that year yet.
     *
     * @param  Builder<Siswa>  $query
     */
    #[Scope]
    protected function anggotaKelas(Builder $query, int $kelasId, int $tahunAjaranId): void
    {
        $tahunAktif = TahunAjaran::query()->whereKey($tahunAjaranId)->where('is_aktif', true)->exists();

        $query->where(fn (Builder $anggota) => $anggota
            ->whereHas('riwayatKelas', fn (Builder $riwayat) => $riwayat->where('kelas_id', $kelasId)->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($tahunAktif, fn (Builder $anggota) => $anggota->orWhere(fn (Builder $sekarang) => $sekarang
                ->where('kelas_id', $kelasId)
                ->whereDoesntHave('riwayatKelas', fn (Builder $riwayat) => $riwayat->where('tahun_ajaran_id', $tahunAjaranId)))));
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * @return HasMany<OrangTua, $this>
     */
    public function orangTua(): HasMany
    {
        return $this->hasMany(OrangTua::class);
    }

    /**
     * @return HasOne<OrangTua, $this>
     */
    public function ayah(): HasOne
    {
        return $this->hasOne(OrangTua::class)->where('jenis', JenisOrangTua::Ayah);
    }

    /**
     * @return HasOne<OrangTua, $this>
     */
    public function ibu(): HasOne
    {
        return $this->hasOne(OrangTua::class)->where('jenis', JenisOrangTua::Ibu);
    }

    /**
     * @return HasOne<OrangTua, $this>
     */
    public function wali(): HasOne
    {
        return $this->hasOne(OrangTua::class)->where('jenis', JenisOrangTua::Wali);
    }

    /**
     * @return HasMany<RiwayatKelas, $this>
     */
    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class);
    }

    /**
     * @return HasMany<Rapor, $this>
     */
    public function rapor(): HasMany
    {
        return $this->hasMany(Rapor::class);
    }

    /**
     * @return HasMany<MutasiSiswa, $this>
     */
    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiSiswa::class);
    }

    public function activityTitle(): ?string
    {
        return "{$this->nis} - {$this->nama_lengkap}";
    }
}
