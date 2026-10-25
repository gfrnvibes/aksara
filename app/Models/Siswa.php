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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
     * @return HasMany<Rapor, $this>
     */
    public function rapor(): HasMany
    {
        return $this->hasMany(Rapor::class);
    }

    public function activityTitle(): ?string
    {
        return "{$this->nis} - {$this->nama_lengkap}";
    }
}
