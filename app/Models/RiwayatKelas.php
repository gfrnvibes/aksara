<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\RiwayatKelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The class a student sat in during one academic year.
 *
 * @property int $id
 * @property int $siswa_id
 * @property int $tahun_ajaran_id
 * @property int $kelas_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('riwayat_kelas')]
#[Fillable(['siswa_id', 'tahun_ajaran_id', 'kelas_id'])]
class RiwayatKelas extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<RiwayatKelasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function activityTitle(): ?string
    {
        return "{$this->siswa->nama_lengkap} - {$this->tahunAjaran->nama} ({$this->kelas->nama})";
    }
}
