<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Bloom's revised levels (C1–C6), each also shown as the AKM/ASPD level L1–L3 it falls under, so a teacher can
 * work with whichever scheme the exam uses.
 */
enum LevelKognitif: string implements HasLabel
{
    case C1 = 'C1';
    case C2 = 'C2';
    case C3 = 'C3';
    case C4 = 'C4';
    case C5 = 'C5';
    case C6 = 'C6';

    public function getLabel(): string
    {
        $nama = match ($this) {
            self::C1 => 'Mengingat',
            self::C2 => 'Memahami',
            self::C3 => 'Menerapkan',
            self::C4 => 'Menganalisis',
            self::C5 => 'Mengevaluasi',
            self::C6 => 'Mencipta',
        };

        return "{$this->value} {$nama} · {$this->level()}";
    }

    /**
     * L1 pengetahuan & pemahaman, L2 aplikasi, L3 penalaran.
     */
    public function level(): string
    {
        return match ($this) {
            self::C1, self::C2 => 'L1',
            self::C3 => 'L2',
            self::C4, self::C5, self::C6 => 'L3',
        };
    }

    /**
     * Suggests a cognitive level from Indonesian Bloom operational verbs in a learning objective (TP).
     */
    public static function dariTeks(?string $teks): ?self
    {
        $teks = mb_strtolower(trim((string) $teks));
        if ($teks === '') {
            return null;
        }

        $pola = [
            self::C6->value => 'mencipta(?:kan)?|merancang|mendesain|menyusun|mengarang|mengembangkan|merumuskan|membangun|memproduksi|mengkreasi|mengkonstruksi|memodifikasi',
            self::C5->value => 'mengevaluasi|menilai|mengkritik|membuktikan|memvalidasi|memutuskan|menyimpulkan|merekomendasikan|mempertahankan',
            self::C4->value => 'menganalisis|menelaah|memecahkan|membandingkan|membedakan|mengaitkan|menghubungkan|mendiagnosis|mendeteksi|menguraikan|menguji',
            self::C3->value => 'menerapkan|menggunakan|menghitung|mempraktikkan|melaksanakan|mendemonstrasikan|menyelesaikan|mengoperasikan|menentukan|mengimplementasikan|mempresentasikan|membuat|menulis',
            self::C2->value => 'memahami|menjelaskan|merangkum|meringkas|mencontohkan|mengartikan|menerjemahkan|mengklasifikasikan|mengelompokkan|menafsirkan|memperkirakan',
            self::C1->value => 'mengingat|menyebutkan|mengidentifikasi|menghafal|mengenali|mendaftar|menunjukkan|menyatakan|menamai|mengulang',
        ];

        foreach ($pola as $kode => $kataKerja) {
            if (preg_match('/\b(?:'.$kataKerja.')\b/u', $teks) === 1) {
                return self::from($kode);
            }
        }

        return null;
    }
}
