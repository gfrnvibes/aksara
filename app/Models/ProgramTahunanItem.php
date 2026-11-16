<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single row in the PROTA table: one element / topic for a semester.
 *
 * @property int $id
 * @property int $program_tahunan_id
 * @property int $semester
 * @property int $urutan
 * @property string $elemen_cp
 * @property string $materi_pokok
 * @property string $tujuan_pembelajaran
 * @property int $alokasi_waktu_jp
 * @property int $minggu_efektif
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProgramTahunan $programTahunan
 */
#[Fillable([
    'program_tahunan_id', 'semester', 'urutan', 'elemen_cp', 'materi_pokok',
    'tujuan_pembelajaran', 'alokasi_waktu_jp', 'minggu_efektif', 'keterangan',
])]
class ProgramTahunanItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'urutan' => 'integer',
            'alokasi_waktu_jp' => 'integer',
            'minggu_efektif' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProgramTahunan, $this>
     */
    public function programTahunan(): BelongsTo
    {
        return $this->belongsTo(ProgramTahunan::class);
    }

    /**
     * The semester as an enum value.
     */
    public function semesterEnum(): Semester
    {
        return Semester::from($this->semester);
    }
}
