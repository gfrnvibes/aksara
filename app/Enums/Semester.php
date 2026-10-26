<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum Semester: int implements HasLabel
{
    case Ganjil = 1;
    case Genap = 2;

    /**
     * The semester a date usually falls in: the odd semester runs July–December, the even one January–June.
     */
    public static function berdasarkanTanggal(CarbonInterface $tanggal): self
    {
        return $tanggal->month >= 7 ? self::Ganjil : self::Genap;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Ganjil => 'Semester 1 (Ganjil)',
            self::Genap => 'Semester 2 (Genap)',
        };
    }
}
