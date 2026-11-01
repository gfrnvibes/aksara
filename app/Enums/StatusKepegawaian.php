<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusKepegawaian: string implements HasColor, HasLabel
{
    case Pns = 'pns';
    case Pppk = 'pppk';
    case TetapYayasan = 'gty';
    case TidakTetap = 'gtt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pns => 'PNS',
            self::Pppk => 'PPPK',
            self::TetapYayasan => 'Tetap Yayasan (GTY/PTY)',
            self::TidakTetap => 'Tidak Tetap / Honorer (GTT/PTT)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pns => 'success',
            self::Pppk => 'info',
            self::TetapYayasan => 'primary',
            self::TidakTetap => 'gray',
        };
    }
}
