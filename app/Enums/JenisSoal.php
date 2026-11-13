<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisSoal: string implements HasLabel
{
    case PilihanGanda = 'pg';
    case PgKompleks = 'pg_kompleks';
    case BenarSalah = 'benar_salah';
    case Menjodohkan = 'menjodohkan';
    case Esai = 'esai';

    public function getLabel(): string
    {
        return match ($this) {
            self::PilihanGanda => 'Pilihan Ganda',
            self::PgKompleks => 'Pilihan Ganda Kompleks',
            self::BenarSalah => 'Benar/Salah',
            self::Menjodohkan => 'Menjodohkan',
            self::Esai => 'Esai',
        };
    }

    /**
     * What one row of `opsi` holds for this kind, as written in the form and asked of Gemini.
     */
    public function labelOpsi(): string
    {
        return match ($this) {
            self::PilihanGanda, self::PgKompleks => 'Pilihan Jawaban',
            self::BenarSalah => 'Pernyataan',
            self::Menjodohkan => 'Pasangan',
            self::Esai => '',
        };
    }

    /**
     * The instruction printed above this kind's section of the paper.
     */
    public function petunjukNaskah(): string
    {
        return match ($this) {
            self::PilihanGanda => 'Pilihlah satu jawaban yang paling tepat!',
            self::PgKompleks => 'Pilihlah semua jawaban yang benar! Jawaban benar lebih dari satu.',
            self::BenarSalah => 'Tentukan Benar (B) atau Salah (S) untuk setiap pernyataan!',
            self::Menjodohkan => 'Jodohkan pernyataan di kolom kiri dengan jawaban yang tepat di kolom kanan!',
            self::Esai => 'Jawablah pertanyaan berikut dengan jelas dan tepat!',
        };
    }

    /**
     * The fewest and most rows of `opsi`; PG stops at E because the answer sheet has five bubbles.
     *
     * @return array{int, int}
     */
    public function batasOpsi(): array
    {
        return match ($this) {
            self::PilihanGanda, self::PgKompleks => [2, 5],
            self::BenarSalah => [1, 10],
            self::Menjodohkan => [2, 8],
            self::Esai => [0, 0],
        };
    }
}
