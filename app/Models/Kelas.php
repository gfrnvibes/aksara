<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\KelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tingkat
 * @property string $nama
 * @property string|null $wali_kelas
 * @property string|null $nip_wali_kelas
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('kelas')]
#[Fillable(['tingkat', 'nama', 'wali_kelas', 'nip_wali_kelas', 'is_aktif'])]
class Kelas extends Model implements ProvidesActivityTitle, TerlindungDariHapus
{
    use CatatAktivitas;

    /** @use HasFactory<KelasFactory> */
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
            'tingkat' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

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

    /**
     * Classes offered for new students and report cards.
     *
     * @param  Builder<Kelas>  $query
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /**
     * Limit a class picker to active classes, plus the one already chosen so editing an older record keeps its value.
     *
     * @param  Builder<Kelas>  $query
     * @return Builder<Kelas>
     */
    public static function batasiYangTersedia(Builder $query, ?int $kelasTerpilih = null): Builder
    {
        return $query->where(fn (Builder $kelas) => $kelas
            ->where('is_aktif', true)
            ->when($kelasTerpilih, fn (Builder $kelas, int $id) => $kelas->orWhereKey($id)));
    }

    public function keteranganPemakaian(): ?string
    {
        $jumlahSiswa = $this->siswa()->count();
        $jumlahRapor = $this->rapor()->withTrashed()->count();

        if ($jumlahSiswa === 0 && $jumlahRapor === 0) {
            return null;
        }

        return "Kelas {$this->nama} masih dipakai oleh {$jumlahSiswa} siswa dan {$jumlahRapor} rapor. Nonaktifkan kelas ini jika tidak dipakai lagi.";
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
