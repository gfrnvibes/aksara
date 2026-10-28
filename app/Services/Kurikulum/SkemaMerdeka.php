<?php

namespace App\Services\Kurikulum;

use App\Enums\KelompokMapel;
use App\Models\NilaiRapor;

/**
 * Kurikulum Merdeka (KMA 450/2024, RDM): one final score per subject and a written description of the competence
 * reached, measured against the KKTP. Extracurricular grades are printed as words.
 */
final class SkemaMerdeka implements SkemaPenilaian
{
    public function labelKkm(): string
    {
        return 'KKTP';
    }

    public function kolomNilai(): array
    {
        return ['nilai_akhir' => 'NA'];
    }

    public function labelKolomNilai(string $kolom): string
    {
        return $kolom === 'nilai_akhir' ? 'Nilai Akhir' : $kolom;
    }

    public function pakaiSikapMapel(): bool
    {
        return false;
    }

    public function pakaiCapaianKompetensi(): bool
    {
        return true;
    }

    public function pakaiAkhlakKepribadian(): bool
    {
        return false;
    }

    public function pakaiPredikat(): bool
    {
        return false;
    }

    public function nilaiUntukRataRata(NilaiRapor $nilaiRapor): array
    {
        return $nilaiRapor->nilai_akhir === null ? [] : [$nilaiRapor->nilai_akhir];
    }

    public function labelNilaiEkstrakurikuler(?string $nilai): ?string
    {
        return match ($nilai) {
            'A' => 'Sangat Baik',
            'B' => 'Baik',
            'C' => 'Cukup',
            'D' => 'Kurang',
            default => $nilai,
        };
    }

    /**
     * Elementary grades 1–2 are Fase A, 3–4 Fase B, 5–6 Fase C, junior secondary (MTs) Fase D, and senior
     * secondary Fase E (grade 10) and F (grades 11–12).
     */
    public function fase(int $tingkat): string
    {
        return match (true) {
            $tingkat <= 2 => 'A',
            $tingkat <= 4 => 'B',
            $tingkat <= 6 => 'C',
            $tingkat <= 9 => 'D',
            $tingkat === 10 => 'E',
            default => 'F',
        };
    }

    public function labelKelompok(KelompokMapel $kelompok): string
    {
        return match ($kelompok) {
            KelompokMapel::A => 'Kelompok Mata Pelajaran Umum',
            KelompokMapel::B => 'Muatan Lokal',
        };
    }
}
