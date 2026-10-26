<?php

namespace App\Models;

use App\Enums\KelompokMapel;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
class MataPelajaran extends Model implements ProvidesActivityTitle, TerlindungDariHapus
{
    use CatatAktivitas;

    /** @use HasFactory<MataPelajaranFactory> */
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

    /**
     * @return HasMany<NilaiRapor, $this>
     */
    public function nilaiRapor(): HasMany
    {
        return $this->hasMany(NilaiRapor::class);
    }

    public function keteranganPemakaian(): ?string
    {
        $jumlah = $this->nilaiRapor()->count();

        return $jumlah === 0
            ? null
            : "Mata pelajaran {$this->nama} sudah dinilai di {$jumlah} rapor. Nonaktifkan mata pelajaran ini jika tidak dipakai lagi.";
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
