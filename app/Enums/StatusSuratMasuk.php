<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusSuratMasuk: string implements HasColor, HasLabel
{
    case Baru = 'baru';
    case Didisposisikan = 'didisposisikan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Didisposisikan => 'Didisposisikan',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Baru => 'warning',
            self::Didisposisikan => 'info',
            self::Selesai => 'success',
        };
    }
}
