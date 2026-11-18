<?php

namespace App\Models;

use App\Enums\Semester;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An exam period / activity (e.g., PAS Ganjil 2026/2027) for generating bulk exam cards and room seating maps.
 *
 * @property int $id
 * @property string $nama
 * @property int $tahun_ajaran_id
 * @property Semester $semester
 * @property Carbon|null $tanggal_mulai
 * @property Carbon|null $tanggal_selesai
 * @property string|null $prefix_nomor_peserta
 * @property int|null $penanda_tangan_id
 * @property int|null $ketua_panitia_id
 * @property int|null $kop_surat_id
 * @property string|null $catatan_kartu
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TahunAjaran $tahunAjaran
 * @property-read PenandaTangan|null $penandaTangan
 * @property-read PenandaTangan|null $ketuaPanitia
 * @property-read KopSurat|null $kopSurat
 * @property-read Collection<int, RuangUjian> $ruang
 * @property-read Collection<int, PenempatanUjian> $penempatan
 */
#[Fillable([
    'nama',
    'tahun_ajaran_id',
    'semester',
    'tanggal_mulai',
    'tanggal_selesai',
    'prefix_nomor_peserta',
    'penanda_tangan_id',
    'ketua_panitia_id',
    'kop_surat_id',
    'catatan_kartu',
    'user_id',
])]
class KegiatanUjian extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
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
    public function penandaTangan(): BelongsTo
    {
        return $this->belongsTo(PenandaTangan::class);
    }

    /**
     * @return BelongsTo<PenandaTangan, $this>
     */
    public function ketuaPanitia(): BelongsTo
    {
        return $this->belongsTo(PenandaTangan::class, 'ketua_panitia_id');
    }

    /**
     * @return BelongsTo<KopSurat, $this>
     */
    public function kopSurat(): BelongsTo
    {
        return $this->belongsTo(KopSurat::class);
    }

    /**
     * @return HasMany<RuangUjian, $this>
     */
    public function ruang(): HasMany
    {
        return $this->hasMany(RuangUjian::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * @return HasMany<PenempatanUjian, $this>
     */
    public function penempatan(): HasMany
    {
        return $this->hasMany(PenempatanUjian::class);
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
