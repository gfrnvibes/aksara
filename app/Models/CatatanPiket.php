<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\TanggalHarian;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $id
 * @property Carbon $tanggal
 * @property-read Kelas|null $kelas
 * @property-read Siswa|null $siswa
 * @property-read Gtk|null $gtk
 * @property-read User $pencatat
 */
#[Guarded(['id'])]
class CatatanPiket extends Model
{
    use CatatAktivitas;
    use TanggalHarian;

    public const array JENIS = ['guru_tidak_hadir' => 'Guru tidak hadir', 'kelas_kosong' => 'Kelas kosong', 'terlambat' => 'Siswa terlambat', 'izin_keluar' => 'Siswa izin keluar', 'lainnya' => 'Kejadian lainnya'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /** @return BelongsTo<Gtk, $this> */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }
}
