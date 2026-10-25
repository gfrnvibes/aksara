<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum KeputusanKenaikan: string implements HasColor, HasLabel
{
    case Naik = 'naik';
    case TinggalDiKelas = 'tinggal';
    case Lulus = 'lulus';

    public function getLabel(): string
    {
        return match ($this) {
            self::Naik => 'Naik Kelas',
            self::TinggalDiKelas => 'Tinggal di Kelas',
            self::Lulus => 'Lulus',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Naik, self::Lulus => 'success',
            self::TinggalDiKelas => 'danger',
        };
    }
}
