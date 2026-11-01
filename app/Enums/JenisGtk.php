<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisGtk: string implements HasLabel
{
    case Guru = 'guru';
    case TenagaKependidikan = 'tendik';

    public function getLabel(): string
    {
        return match ($this) {
            self::Guru => 'Guru',
            self::TenagaKependidikan => 'Tenaga Kependidikan',
        };
    }
}
