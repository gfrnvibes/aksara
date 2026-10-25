<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusSiswa: string implements HasColor, HasLabel
{
    case Aktif = 'aktif';
    case Lulus = 'lulus';
    case Pindah = 'pindah';
    case Keluar = 'keluar';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Lulus => 'info',
            self::Pindah => 'warning',
            self::Keluar => 'danger',
        };
    }
}
