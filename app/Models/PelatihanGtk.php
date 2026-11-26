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
 * @property string $nama_kegiatan
 * @property string $jenis
 * @property string|null $penyelenggara
 * @property bool $dapat_bantuan_biaya
 * @property string|null $berkas_path
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Gtk $gtk
 */
#[Guarded(['id'])]
class PelatihanGtk extends Model
{
    /** @var array<string, string> */
    public const array JENIS = [
        'kkg_mgmp' => 'KKG / MGMP',
        'workshop' => 'Workshop / Seminar',
        'bimtek' => 'Bimtek / Diklat Teknis',
        'pelatihan_kepala' => 'Pengembangan Diri Kepala Madrasah (KKM)',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'dapat_bantuan_biaya' => 'boolean',
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
