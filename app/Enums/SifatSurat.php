<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SifatSurat: string implements HasColor, HasLabel
{
    case Biasa = 'biasa';
    case Penting = 'penting';
    case Segera = 'segera';
    case Rahasia = 'rahasia';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Biasa => 'gray',
            self::Penting => 'warning',
            self::Segera => 'danger',
            self::Rahasia => 'info',
        };
    }
}
