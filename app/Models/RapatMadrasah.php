<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property Carbon $tanggal
 * @property string $judul
 * @property string $jenis
 * @property bool $melibatkan_guru
 * @property bool $melibatkan_tendik
 * @property bool $melibatkan_komite
 * @property bool $melibatkan_yayasan
 * @property string|null $notulensi
 * @property string|null $berkas_path
 * @property-read TahunAjaran $tahunAjaran
 */
#[Guarded(['id'])]
class RapatMadrasah extends Model
{
    /** @var array<string, string> */
    public const array JENIS = [
        'evaluasi_diri' => 'Rapat Evaluasi Diri (SNP)',
        'edm_rkam' => 'Rapat Penyusunan EDM & e-RKAM',
        'komite' => 'Pertemuan Komite / Orang Tua / Yayasan',
        'rutin' => 'Rapat Rutin Guru & Tendik',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'melibatkan_guru' => 'boolean',
            'melibatkan_tendik' => 'boolean',
            'melibatkan_komite' => 'boolean',
            'melibatkan_yayasan' => 'boolean',
        ];
    }

    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
