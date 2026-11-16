<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single entry row in Program Semester (PROSEM).
 *
 * @property int $id
 * @property int $program_semester_id
 * @property int $urutan
 * @property string $tujuan_pembelajaran
 * @property string $materi_pokok
 * @property int $alokasi_waktu_jp
 * @property string $bulan
 * @property string $minggu_ke
 * @property array<string, list<int>>|null $distribusi_minggu
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProgramSemester $programSemester
 */
#[Fillable([
    'program_semester_id', 'urutan', 'tujuan_pembelajaran', 'materi_pokok',
    'alokasi_waktu_jp', 'bulan', 'minggu_ke', 'distribusi_minggu', 'keterangan',
])]
class ProgramSemesterItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'alokasi_waktu_jp' => 'integer',
            'distribusi_minggu' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProgramSemester, $this>
     */
    public function programSemester(): BelongsTo
    {
        return $this->belongsTo(ProgramSemester::class);
    }
}
