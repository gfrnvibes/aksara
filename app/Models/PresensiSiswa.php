<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\TanggalHarian;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $id
 * @property int $siswa_id
 * @property int $pencatat_id
 * @property int $kelas_id
 * @property string $status
 * @property int $terlambat_menit
 * @property string|null $catatan
 * @property Carbon $tanggal
 * @property-read Siswa $siswa
 */
#[Guarded(['id'])]
class PresensiSiswa extends Model
{
    use CatatAktivitas;
    use TanggalHarian;

    public const array STATUS = ['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'terlambat_menit' => 'integer'];
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }
}
