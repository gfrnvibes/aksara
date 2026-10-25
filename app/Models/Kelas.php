<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\KelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tingkat
 * @property string $nama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('kelas')]
#[Fillable(['tingkat', 'nama'])]
class Kelas extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<KelasFactory> */
    use HasFactory;

    /**
     * @return HasMany<Siswa, $this>
     */
    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
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
        return $this->nama;
    }
}
