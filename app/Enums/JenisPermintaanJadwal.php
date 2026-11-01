<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JenisPermintaanJadwal: string implements HasColor, HasLabel
{
    /**
     * The teacher cannot (or would rather not) teach at the chosen times.
     */
    case TidakBisa = 'tidak_bisa';

    /**
     * The teacher would like to teach only at the chosen times.
     */
    case Ingin = 'ingin';

    public function getLabel(): string
    {
        return match ($this) {
            self::TidakBisa => 'Tidak bisa mengajar pada',
            self::Ingin => 'Ingin mengajar hanya pada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TidakBisa => 'danger',
            self::Ingin => 'success',
        };
    }
}
