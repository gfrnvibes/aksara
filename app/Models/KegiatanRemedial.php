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
 * @property int $kelas_id
 * @property int $mata_pelajaran_id
 * @property Carbon $tanggal
 * @property string $jenis
 * @property int $jumlah_sasaran
 * @property int $jumlah_hadir
 * @property string|null $catatan
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Gtk $gtk
 * @property-read Kelas $kelas
 * @property-read MataPelajaran $mataPelajaran
 */
#[Guarded(['id'])]
class KegiatanRemedial extends Model
{
    /** @var array<string, string> */
    public const array JENIS = [
        'remedial' => 'Remedial',
        'pengayaan' => 'Pengayaan',
        'konseling' => 'Pelayanan Konseling',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_sasaran' => 'integer',
            'jumlah_hadir' => 'integer',
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

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return BelongsTo<MataPelajaran, $this> */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
