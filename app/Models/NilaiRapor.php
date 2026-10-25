<?php

namespace App\Models;

use Database\Factories\NilaiRaporFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rapor_id
 * @property int $mata_pelajaran_id
 * @property int $kkm
 * @property int|null $nilai_pengetahuan
 * @property string|null $predikat_pengetahuan
 * @property int|null $nilai_keterampilan
 * @property string|null $predikat_keterampilan
 * @property string|null $sikap
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id', 'predikat_pengetahuan', 'predikat_keterampilan'])]
class NilaiRapor extends Model
{
    /** @use HasFactory<NilaiRaporFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kkm' => 'integer',
            'nilai_pengetahuan' => 'integer',
            'nilai_keterampilan' => 'integer',
        ];
    }

    /**
     * Keep the letter grades in sync with the numeric scores.
     */
    protected static function booted(): void
    {
        static::saving(function (NilaiRapor $nilaiRapor): void {
            $nilaiRapor->predikat_pengetahuan = static::hitungPredikat($nilaiRapor->nilai_pengetahuan, $nilaiRapor->kkm);
            $nilaiRapor->predikat_keterampilan = static::hitungPredikat($nilaiRapor->nilai_keterampilan, $nilaiRapor->kkm);
        });
    }

    /**
     * Convert a 1-100 score into a letter grade, splitting the range above the KKM into three equal intervals.
     */
    public static function hitungPredikat(?int $nilai, int $kkm): ?string
    {
        if ($nilai === null) {
            return null;
        }

        if ($nilai < $kkm) {
            return 'D';
        }

        $interval = (100 - $kkm) / 3;

        return match (true) {
            $nilai >= $kkm + (2 * $interval) => 'A',
            $nilai >= $kkm + $interval => 'B',
            default => 'C',
        };
    }

    /**
     * @return BelongsTo<Rapor, $this>
     */
    public function rapor(): BelongsTo
    {
        return $this->belongsTo(Rapor::class);
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
