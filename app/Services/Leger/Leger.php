<?php

namespace App\Services\Leger;

use App\Enums\Semester;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Kurikulum\SkemaPenilaian;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The grade recap of one class for one semester: every student's scores per subject — knowledge and skill for
 * K13, the final score for Kurikulum Merdeka — with their total, average and rank.
 *
 * @phpstan-type NilaiMapel array{skor: array<string, int|null>, kkm: int}
 * @phpstan-type BarisLeger array{siswa: Siswa, nilai: array<int, NilaiMapel>, jumlah: int, rata_rata: float|null, peringkat: int|null}
 */
final readonly class Leger
{
    /**
     * @param  Collection<int, MataPelajaran>  $mataPelajaran
     * @param  list<BarisLeger>  $baris  ordered by student name
     */
    public function __construct(
        public Kelas $kelas,
        public TahunAjaran $tahunAjaran,
        public Semester $semester,
        public SkemaPenilaian $skema,
        public Collection $mataPelajaran,
        public array $baris,
    ) {}

    /**
     * The score columns shown under each subject, column => short label.
     *
     * @return array<string, string>
     */
    public function kolom(): array
    {
        return $this->skema->kolomNilai();
    }

    /**
     * What the short column labels stand for, e.g. "P = Pengetahuan (KI-3), K = Keterampilan (KI-4)".
     */
    public function keteranganKolom(): string
    {
        return collect($this->kolom())
            ->map(fn (string $singkatan, string $kolom): string => "{$singkatan} = {$this->skema->labelKolomNilai($kolom)}")
            ->implode(', ');
    }

    public function judul(): string
    {
        return "Leger Nilai Kelas {$this->kelas->nama} - {$this->semester->getLabel()} {$this->tahunAjaran->nama}";
    }

    public function namaFile(string $ekstensi): string
    {
        return Str::slug("leger {$this->kelas->nama} {$this->tahunAjaran->nama} semester {$this->semester->name}").".{$ekstensi}";
    }

    /**
     * Class average of every score column of each subject, keyed by subject id then column.
     *
     * @return array<int, array<string, float|null>>
     */
    public function rataRataMapel(): array
    {
        return $this->mataPelajaran
            ->mapWithKeys(fn (MataPelajaran $mataPelajaran): array => [$mataPelajaran->id => collect($this->kolom())
                ->mapWithKeys(fn (string $singkatan, string $kolom): array => [$kolom => $this->rataRata(array_map(
                    fn (array $baris): ?int => $baris['nilai'][$mataPelajaran->id]['skor'][$kolom] ?? null,
                    $this->baris,
                ))])
                ->all()])
            ->all();
    }

    /**
     * @param  array<int, int|null>  $nilai
     */
    private function rataRata(array $nilai): ?float
    {
        $terisi = array_filter($nilai, is_int(...));

        return $terisi === [] ? null : round(array_sum($terisi) / count($terisi), 2);
    }
}
