<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SifatPermintaanJadwal: string implements HasColor, HasLabel
{
    /**
     * Never broken by the automatic schedule, e.g. the teacher teaches at another school then.
     */
    case Wajib = 'wajib';

    /**
     * Followed when the rest of the schedule allows; the report lists the ones that could not be.
     */
    case Diusahakan = 'diusahakan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wajib => 'Wajib (tidak boleh dilanggar)',
            self::Diusahakan => 'Diusahakan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Wajib => 'danger',
            self::Diusahakan => 'warning',
        };
    }
}
