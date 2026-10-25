<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Agama: string implements HasLabel
{
    case Islam = 'islam';
    case Kristen = 'kristen';
    case Katolik = 'katolik';
    case Hindu = 'hindu';
    case Buddha = 'buddha';
    case Konghucu = 'konghucu';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
