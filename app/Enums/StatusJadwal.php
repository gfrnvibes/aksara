<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusJadwal: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Terbit = 'terbit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Terbit => 'Terbit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Terbit => 'success',
        };
    }
}
