<?php

namespace App\Services\CapaianKompetensi;

use App\Models\NilaiRapor;

/**
 * Writes the competence description of a subject from its final score, in the RDM wording: the mastery level is
 * read against the KKTP the same way the A–D letter grades are, and the material is the one the teacher named or
 * the subject itself.
 */
class CapaianBerbasisAturan
{
    /**
     * The opening phrase of each mastery level, keyed by the A–D grade of the score.
     *
     * @var array<string, string>
     */
    public const array AWALAN = [
        'A' => 'Menunjukkan penguasaan yang sangat baik dalam',
        'B' => 'Menunjukkan penguasaan yang baik dalam',
        'C' => 'Menunjukkan penguasaan yang cukup baik dalam',
        'D' => 'Perlu bimbingan dalam',
    ];

    /**
     * The opening phrase for a final score measured against the KKTP.
     */
    public static function awalan(int $nilai, int $kktp): string
    {
        return static::AWALAN[NilaiRapor::hitungPredikat($nilai, $kktp) ?? 'D'];
    }

    public function deskripsi(string $mataPelajaran, int $nilai, int $kktp, ?string $materi = null): string
    {
        $materi = filled($materi) ? rtrim(trim($materi), '.') : "memahami dan menerapkan materi {$mataPelajaran}";

        return static::awalan($nilai, $kktp)." {$materi}.";
    }
}
