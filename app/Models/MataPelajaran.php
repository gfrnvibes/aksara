<?php

namespace App\Models;

use App\Enums\KelompokMapel;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property KelompokMapel $kelompok
 * @property int $kkm
 * @property int $urutan
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'kelompok', 'kkm', 'urutan', 'is_aktif'])]
class MataPelajaran extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<MataPelajaranFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kelompok' => KelompokMapel::class,
            'kkm' => 'integer',
            'urutan' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * Scope to active subjects in report-card order.
     *
     * @param  Builder<MataPelajaran>  $query
     */
    #[Scope]
    protected function aktifBerurutan(Builder $query): void
    {
        $query->where('is_aktif', true)->orderBy('kelompok')->orderBy('urutan');
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
