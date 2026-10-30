<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of outgoing letter, each with the code used in its number.
 */
enum JenisSuratKeluar: string implements HasLabel
{
    case Undangan = 'undangan';
    case Pemberitahuan = 'pemberitahuan';
    case Keterangan = 'keterangan';
    case Tugas = 'tugas';
    case Permohonan = 'permohonan';
    case Edaran = 'edaran';
    case Keputusan = 'keputusan';
    case Rekomendasi = 'rekomendasi';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Undangan => 'Surat Undangan',
            self::Pemberitahuan => 'Surat Pemberitahuan',
            self::Keterangan => 'Surat Keterangan',
            self::Tugas => 'Surat Tugas',
            self::Permohonan => 'Surat Permohonan',
            self::Edaran => 'Surat Edaran',
            self::Keputusan => 'Surat Keputusan',
            self::Rekomendasi => 'Surat Rekomendasi',
            self::Lainnya => 'Surat Lainnya',
        };
    }

    /**
     * The code of this kind in the letter number, e.g. "UND" in 012/UND/MTs-AT/X/2026.
     */
    public function kode(): string
    {
        return match ($this) {
            self::Undangan => 'UND',
            self::Pemberitahuan => 'PEMB',
            self::Keterangan => 'KET',
            self::Tugas => 'ST',
            self::Permohonan => 'PERM',
            self::Edaran => 'SE',
            self::Keputusan => 'SK',
            self::Rekomendasi => 'REK',
            self::Lainnya => 'UM',
        };
    }
}
