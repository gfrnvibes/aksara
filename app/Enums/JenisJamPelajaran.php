<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A row of the daily bell schedule: a lesson period (JP), a break, or a fixed activity such as prayers.
 */
enum JenisJamPelajaran: string implements HasColor, HasLabel
{
    case Pelajaran = 'pelajaran';
    case Istirahat = 'istirahat';
    case Kegiatan = 'kegiatan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pelajaran => 'Jam Pelajaran',
            self::Istirahat => 'Istirahat',
            self::Kegiatan => 'Kegiatan (shalat, tadarus, upacara)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pelajaran => 'primary',
            self::Istirahat => 'gray',
            self::Kegiatan => 'info',
        };
    }
}
