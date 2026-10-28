<?php

namespace App\Services\Kurikulum;

use App\Enums\KelompokMapel;
use App\Models\NilaiRapor;

/**
 * How a curriculum grades a subject on the report card. Everything that reads or writes subject grades — the
 * report card form, class grade entry, import templates, the leger and the printouts — asks the scheme instead
 * of assuming one curriculum.
 */
interface SkemaPenilaian
{
    /**
     * The name of the passing threshold: KKM (K13) or KKTP (Merdeka).
     */
    public function labelKkm(): string;

    /**
     * The numeric score columns of a subject, column => short label, in the order they are shown.
     *
     * @return array<string, string>
     */
    public function kolomNilai(): array;

    /**
     * The long label of a score column, e.g. "Pengetahuan (KI-3)".
     */
    public function labelKolomNilai(string $kolom): string;

    /**
     * Whether each subject also gets an attitude grade (SB/B/C/K).
     */
    public function pakaiSikapMapel(): bool;

    /**
     * Whether each subject gets a written description of the competence reached.
     */
    public function pakaiCapaianKompetensi(): bool;

    /**
     * Whether the report card carries the madrasah's Akhlak and Kepribadian grades.
     */
    public function pakaiAkhlakKepribadian(): bool;

    /**
     * Whether letter grades (A–D) are derived from the scores.
     */
    public function pakaiPredikat(): bool;

    /**
     * The scores of one subject that count towards a student's average and rank.
     *
     * @return list<int>
     */
    public function nilaiUntukRataRata(NilaiRapor $nilaiRapor): array;

    /**
     * How an extracurricular grade (stored as A–D) is printed.
     */
    public function labelNilaiEkstrakurikuler(?string $nilai): ?string;

    /**
     * The learning phase of a grade level, e.g. Fase D for MTs, or null when the curriculum has none.
     */
    public function fase(int $tingkat): ?string;

    /**
     * The heading of a subject group on the printed report card.
     */
    public function labelKelompok(KelompokMapel $kelompok): string;
}
