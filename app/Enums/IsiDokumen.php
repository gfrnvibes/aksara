<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum IsiDokumen: string implements HasDescription, HasLabel
{
    case Siswa = 'siswa';
    case Nilai = 'nilai';
    case Keduanya = 'keduanya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Siswa => 'Data Siswa saja',
            self::Nilai => 'Data Nilai Siswa saja',
            self::Keduanya => 'Data Siswa dan Nilai Siswa',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Siswa => 'Biodata buku induk (bagian A–L) beserta orang tua/wali.',
            self::Nilai => 'Nilai rapor per semester.',
            self::Keduanya => 'Biodata buku induk lengkap dengan nilai seluruh semester.',
        };
    }

    public function termasukSiswa(): bool
    {
        return $this !== self::Nilai;
    }

    public function termasukNilai(): bool
    {
        return $this !== self::Siswa;
    }
}
