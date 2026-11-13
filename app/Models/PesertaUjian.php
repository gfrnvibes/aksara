<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student sitting an exam: the packet they got, their answers and the score worked out from them.
 *
 * @property int $id
 * @property int $ujian_id
 * @property int $siswa_id
 * @property int|null $kelas_id The class at the time of the exam.
 * @property string $paket
 * @property array<int, string|float|null>|null $jawaban Per question id: the letters marked, as printed in the packet ("B", "AC", "BSB", "CAB"), or the essay score.
 * @property array<int, float>|null $skor Per question id.
 * @property string|null $nilai 0–100; null until answers are entered (absent).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ujian $ujian
 * @property-read Siswa $siswa
 * @property-read Kelas|null $kelas
 */
#[Fillable(['ujian_id', 'siswa_id', 'kelas_id', 'paket', 'jawaban', 'skor', 'nilai'])]
class PesertaUjian extends Model
{
    protected function casts(): array
    {
        return ['jawaban' => 'array', 'skor' => 'array', 'nilai' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Ujian, $this>
     */
    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }
}
