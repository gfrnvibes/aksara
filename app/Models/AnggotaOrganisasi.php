<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ekstrakurikuler_id
 * @property int $tahun_ajaran_id
 * @property int $siswa_id
 * @property string $jabatan
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ekstrakurikuler $ekstrakurikuler
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Siswa $siswa
 */
#[Guarded(['id'])]
class AnggotaOrganisasi extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /**
     * @return BelongsTo<Ekstrakurikuler, $this>
     */
    public function ekstrakurikuler(): BelongsTo
    {
        return $this->belongsTo(Ekstrakurikuler::class);
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
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
        return $this->siswa->nama_lengkap.' ('.$this->ekstrakurikuler->nama.')';
    }
}
