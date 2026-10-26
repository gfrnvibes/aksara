<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What the end-of-year promotion does with one student.
 */
enum TindakanKenaikan: string implements HasColor, HasLabel
{
    case Naik = 'naik';
    case TinggalDiKelas = 'tinggal';
    case Lulus = 'lulus';
    case Lewati = 'lewati';

    public function getLabel(): string
    {
        return match ($this) {
            self::Naik => 'Naik Kelas',
            self::TinggalDiKelas => 'Tinggal di Kelas',
            self::Lulus => 'Lulus',
            self::Lewati => 'Lewati (belum diproses)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Naik, self::Lulus => 'success',
            self::TinggalDiKelas => 'danger',
            self::Lewati => 'gray',
        };
    }

    /**
     * The action suggested by the decision on the even-semester report card.
     */
    public static function dariKeputusan(?KeputusanKenaikan $keputusan): self
    {
        return match ($keputusan) {
            KeputusanKenaikan::Naik => self::Naik,
            KeputusanKenaikan::TinggalDiKelas => self::TinggalDiKelas,
            KeputusanKenaikan::Lulus => self::Lulus,
            null => self::Lewati,
        };
    }
}
