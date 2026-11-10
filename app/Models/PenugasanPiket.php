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
 * @property Carbon $tanggal
 * @property-read Gtk $gtk
 */
#[Guarded(['id'])]
class PenugasanPiket extends Model
{
    use CatatAktivitas;
    use TanggalHarian;

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    /** @return BelongsTo<Gtk, $this> */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }
}
