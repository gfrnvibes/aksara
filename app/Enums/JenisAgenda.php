<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JenisAgenda: string implements HasColor, HasLabel
{
    case Libur = 'libur';
    case Ujian = 'ujian';
    case Kegiatan = 'kegiatan';
    case Rapat = 'rapat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Libur => 'Libur',
            self::Ujian => 'Ujian / Asesmen',
            self::Kegiatan => 'Kegiatan Madrasah',
            self::Rapat => 'Rapat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Libur => 'danger',
            self::Ujian => 'warning',
            self::Kegiatan => 'success',
            self::Rapat => 'info',
        };
    }

    /**
     * Whether lessons do not take place on these days, so they are not effective school days.
     */
    public function meliburkanPelajaran(): bool
    {
        return $this === self::Libur;
    }
}
