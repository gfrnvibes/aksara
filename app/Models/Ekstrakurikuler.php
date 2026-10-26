<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\EkstrakurikulerFactory;
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
 * @property bool $is_wajib
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'is_wajib', 'is_aktif'])]
class Ekstrakurikuler extends Model implements ProvidesActivityTitle, TerlindungDariHapus
{
    use CatatAktivitas;

    /** @use HasFactory<EkstrakurikulerFactory> */
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
            'is_wajib' => 'boolean',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<NilaiEkstrakurikuler, $this>
     */
    public function nilaiEkstrakurikuler(): HasMany
    {
        return $this->hasMany(NilaiEkstrakurikuler::class);
    }

    /**
     * Extracurriculars offered on new report cards.
     *
     * @param  Builder<Ekstrakurikuler>  $query
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /**
     * Limit an extracurricular picker to active ones, plus the one already chosen so editing an older report card keeps its value.
     *
     * @param  Builder<Ekstrakurikuler>  $query
     * @return Builder<Ekstrakurikuler>
     */
    public static function batasiYangTersedia(Builder $query, ?int $ekstrakurikulerTerpilih = null): Builder
    {
        return $query->where(fn (Builder $ekstrakurikuler) => $ekstrakurikuler
            ->where('is_aktif', true)
            ->when($ekstrakurikulerTerpilih, fn (Builder $ekstrakurikuler, int $id) => $ekstrakurikuler->orWhereKey($id)));
    }

    public function keteranganPemakaian(): ?string
    {
        $jumlah = $this->nilaiEkstrakurikuler()->count();

        return $jumlah === 0
            ? null
            : "Ekstrakurikuler {$this->nama} sudah dinilai di {$jumlah} rapor. Nonaktifkan ekstrakurikuler ini jika tidak dipakai lagi.";
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
