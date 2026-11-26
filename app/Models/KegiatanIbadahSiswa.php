<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $kelas_id
 * @property Carbon $tanggal
 * @property string $jenis
 * @property int $jumlah_siswa
 * @property int $jumlah_ikut
 * @property string|null $alasan_ketidakikutsertaan
 * @property int|null $pencatat_id
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Kelas $kelas
 */
#[Guarded(['id'])]
class KegiatanIbadahSiswa extends Model
{
    /** @var array<string, string> */
    public const array JENIS = [
        'sholat_berjamaah' => 'Sholat Berjamaah',
        'tadarus' => 'Tadarus Bersama',
        'infaq_mingguan' => 'Infaq Mingguan',
        'ibadah_lain' => 'Ibadah Rutin Lainnya',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_siswa' => 'integer',
            'jumlah_ikut' => 'integer',
        ];
    }

    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }
}
