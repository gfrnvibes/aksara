<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JenisMutasi: string implements HasColor, HasLabel
{
    /** Joins from another school. */
    case Masuk = 'masuk';

    /** Moves to another school. */
    case Pindah = 'pindah';

    /** Leaves without moving to another school. */
    case Keluar = 'keluar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Pindah Masuk',
            self::Pindah => 'Pindah Keluar',
            self::Keluar => 'Keluar / Mengundurkan Diri',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Masuk => 'success',
            self::Pindah => 'warning',
            self::Keluar => 'danger',
        };
    }

    /**
     * Whether the student leaves the madrasah.
     */
    public function meninggalkanSekolah(): bool
    {
        return $this !== self::Masuk;
    }

    /**
     * The student status the transfer leaves behind.
     */
    public function statusSiswa(): StatusSiswa
    {
        return match ($this) {
            self::Masuk => StatusSiswa::Aktif,
            self::Pindah => StatusSiswa::Pindah,
            self::Keluar => StatusSiswa::Keluar,
        };
    }

    /**
     * What the school field holds for this kind of transfer.
     */
    public function labelSekolah(): string
    {
        return $this === self::Masuk ? 'Sekolah Asal' : 'Sekolah Tujuan';
    }
}
