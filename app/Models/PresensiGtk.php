<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\TanggalHarian;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $id
 * @property int $gtk_id
 * @property string $status
 * @property Carbon $tanggal
 * @property-read Gtk $gtk
 * @property-read User $pencatat
 */
#[Guarded(['id'])]
class PresensiGtk extends Model
{
    use CatatAktivitas;
    use TanggalHarian;

    public const array STATUS = ['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'dinas_luar' => 'Dinas luar', 'alpa' => 'Alpa'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'dicatat_pada' => 'datetime'];
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
