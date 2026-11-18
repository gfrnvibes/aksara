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
 * @property int $siswa_id
 * @property int $tahun_ajaran_id
 * @property int $pencatat_id
 * @property Carbon $tanggal
 * @property string $nama_prestasi
 * @property string $tingkat
 * @property string|null $peringkat
 * @property string|null $penyelenggara
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Siswa $siswa
 * @property-read TahunAjaran $tahunAjaran
 * @property-read User $pencatat
 */
#[Guarded(['id'])]
class PrestasiSiswa extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    public function activityTitle(): ?string
    {
        return $this->siswa->nama_lengkap.' — '.$this->nama_prestasi;
    }
}
