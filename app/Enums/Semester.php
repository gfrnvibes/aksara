<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Semester: int implements HasLabel
{
    case Ganjil = 1;
    case Genap = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Ganjil => 'Semester 1 (Ganjil)',
            self::Genap => 'Semester 2 (Genap)',
        };
    }
}
