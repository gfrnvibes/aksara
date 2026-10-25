<?php

namespace App\Models;

use App\Enums\Agama;
use App\Enums\JenisOrangTua;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\OrangTuaFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $siswa_id
 * @property JenisOrangTua $jenis
 * @property string $nama
 * @property string|null $tempat_lahir
 * @property Carbon|null $tanggal_lahir
 * @property Agama|null $agama
 * @property string|null $pendidikan
 * @property string|null $pekerjaan
 * @property string|null $penghasilan
 * @property string|null $alamat
 * @property string|null $nomor_telepon
 * @property bool $masih_hidup
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id'])]
class OrangTua extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<OrangTuaFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisOrangTua::class,
            'agama' => Agama::class,
            'tanggal_lahir' => 'date',
            'masih_hidup' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function activityTitle(): ?string
    {
        return "{$this->jenis->getLabel()} {$this->nama}";
    }
}
