<?php

namespace App\Models;

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
class Kelas extends Model
{
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
}
