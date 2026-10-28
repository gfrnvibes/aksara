<?php

namespace App\Services\Kurikulum;

use App\Enums\KelompokMapel;
use App\Models\NilaiRapor;

/**
 * Kurikulum 2013: a knowledge (KI-3) and a skill (KI-4) score with letter grades, an attitude grade per subject,
 * and the madrasah's Akhlak and Kepribadian grades.
 */
final class SkemaK13 implements SkemaPenilaian
{
    public function labelKkm(): string
    {
        return 'KKM';
    }

    public function kolomNilai(): array
    {
        return ['nilai_pengetahuan' => 'P', 'nilai_keterampilan' => 'K'];
    }

    public function labelKolomNilai(string $kolom): string
    {
        return match ($kolom) {
            'nilai_pengetahuan' => 'Pengetahuan (KI-3)',
            'nilai_keterampilan' => 'Keterampilan (KI-4)',
            default => $kolom,
        };
    }

    public function pakaiSikapMapel(): bool
    {
        return true;
    }

    public function pakaiCapaianKompetensi(): bool
    {
        return false;
    }

    public function pakaiAkhlakKepribadian(): bool
    {
        return true;
    }

    public function pakaiPredikat(): bool
    {
        return true;
    }

    public function nilaiUntukRataRata(NilaiRapor $nilaiRapor): array
    {
        return array_values(array_filter([$nilaiRapor->nilai_pengetahuan, $nilaiRapor->nilai_keterampilan], is_int(...)));
    }

    public function labelNilaiEkstrakurikuler(?string $nilai): ?string
    {
        return $nilai;
    }

    public function fase(int $tingkat): ?string
    {
        return null;
    }

    public function labelKelompok(KelompokMapel $kelompok): string
    {
        return $kelompok->getLabel();
    }
}
