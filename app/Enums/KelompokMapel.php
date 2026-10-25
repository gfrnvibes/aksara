<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KelompokMapel: string implements HasLabel
{
    case A = 'A';
    case B = 'B';

    public function getLabel(): string
    {
        return 'Kelompok '.$this->value;
    }
}
