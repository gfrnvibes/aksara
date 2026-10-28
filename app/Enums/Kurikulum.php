<?php

namespace App\Enums;

use App\Services\Kurikulum\SkemaK13;
use App\Services\Kurikulum\SkemaMerdeka;
use App\Services\Kurikulum\SkemaPenilaian;
use Filament\Support\Contracts\HasLabel;

/**
 * The curriculum an academic year follows. A new curriculum is a new case plus its grading scheme; the
 * years already graded under another curriculum keep theirs.
 */
enum Kurikulum: string implements HasLabel
{
    case Merdeka = 'merdeka';
    case K13 = 'k13';

    public function getLabel(): string
    {
        return match ($this) {
            self::Merdeka => 'Kurikulum Merdeka',
            self::K13 => 'Kurikulum 2013 (K13)',
        };
    }

    /**
     * The curriculum new academic years follow.
     */
    public static function bawaan(): self
    {
        return self::Merdeka;
    }

    public function skema(): SkemaPenilaian
    {
        return match ($this) {
            self::Merdeka => new SkemaMerdeka,
            self::K13 => new SkemaK13,
        };
    }
}
