<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Accreditation document (one of the 6 Sispena mandatory documents or 14 DKA item narratives) per academic year.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property string $kategori dokumen_wajib | dka_butir
 * @property string $kode dw_1..dw_6 | butir_01..butir_14
 * @property int|null $komponen
 * @property string $judul
 * @property string|null $isi_html
 * @property array<string, mixed>|null $ringkasan_data
 * @property string $status belum | draf | final
 * @property bool $dibantu_ai
 * @property int|null $kepala_madrasah_id
 * @property int|null $ketua_tim_id
 * @property string|null $nama_pengawas
 * @property string|null $nip_pengawas
 * @property string|null $jabatan_pengawas
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read PenandaTangan|null $kepalaMadrasah
 * @property-read PenandaTangan|null $ketuaTim
 */
#[Fillable([
    'tahun_ajaran_id',
    'kategori',
    'kode',
    'komponen',
    'judul',
    'isi_html',
    'ringkasan_data',
    'status',
    'dibantu_ai',
    'kepala_madrasah_id',
    'ketua_tim_id',
    'nama_pengawas',
    'nip_pengawas',
    'jabatan_pengawas',
    'user_id',
])]
class AkreditasiDokumen extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return [
            'komponen' => 'integer',
            'ringkasan_data' => 'array',
            'dibantu_ai' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return BelongsTo<PenandaTangan, $this>
     */
    public function kepalaMadrasah(): BelongsTo
    {
        return $this->belongsTo(PenandaTangan::class, 'kepala_madrasah_id');
    }

    /**
     * @return BelongsTo<PenandaTangan, $this>
     */
    public function ketuaTim(): BelongsTo
    {
        return $this->belongsTo(PenandaTangan::class, 'ketua_tim_id');
    }

    public function activityTitle(): ?string
    {
        return $this->judul;
    }
}
