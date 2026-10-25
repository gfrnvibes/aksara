<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisOrangTua: string implements HasLabel
{
    case Ayah = 'ayah';
    case Ibu = 'ibu';
    case Wali = 'wali';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
