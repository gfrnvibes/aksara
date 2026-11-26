<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $gtk_id
 * @property Carbon $tanggal
 * @property string|null $nomor_sk
 * @property string|null $supervisor
 * @property bool $penilaian_otentik
 * @property string|null $catatan
 * @property string|null $tindak_lanjut
 * @property string|null $berkas_path
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Gtk $gtk
 */
#[Guarded(['id'])]
class SupervisiPembelajaran extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'penilaian_otentik' => 'boolean',
        ];
    }

    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /** @return BelongsTo<Gtk, $this> */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }
}
