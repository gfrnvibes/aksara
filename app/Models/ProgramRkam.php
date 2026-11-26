<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property string $nama_program
 * @property int $jumlah_guru_sasaran
 * @property bool $pakai_edm_rkam
 * @property bool $disahkan_kepala
 * @property string|null $keterangan
 * @property string|null $berkas_path
 * @property-read TahunAjaran $tahunAjaran
 */
#[Guarded(['id'])]
class ProgramRkam extends Model
{
    protected function casts(): array
    {
        return [
            'jumlah_guru_sasaran' => 'integer',
            'pakai_edm_rkam' => 'boolean',
            'disahkan_kepala' => 'boolean',
        ];
    }

    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
