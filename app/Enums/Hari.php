<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

/**
 * School days, numbered as ISO weekdays (Monday = 1) so a date maps straight onto them.
 */
enum Hari: int implements HasLabel
{
    case Senin = 1;
    case Selasa = 2;
    case Rabu = 3;
    case Kamis = 4;
    case Jumat = 5;
    case Sabtu = 6;

    public static function dariTanggal(CarbonInterface $tanggal): ?self
    {
        return self::tryFrom($tanggal->dayOfWeekIso);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Jumat => "Jum'at",
            default => $this->name,
        };
    }
}
