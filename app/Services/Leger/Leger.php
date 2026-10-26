<?php

namespace App\Services\Leger;

use App\Enums\Semester;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The grade recap of one class for one semester: every student's knowledge (P) and skill (K) score per subject,
 * with their total, average and rank.
 *
 * @phpstan-type BarisLeger array{siswa: Siswa, nilai: array<int, array{p: int|null, k: int|null, kkm: int}>, jumlah: int, rata_rata: float|null, peringkat: int|null}
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
        public Collection $mataPelajaran,
        public array $baris,
    ) {}

    public function judul(): string
    {
        return "Leger Nilai Kelas {$this->kelas->nama} - {$this->semester->getLabel()} {$this->tahunAjaran->nama}";
    }

    public function namaFile(string $ekstensi): string
    {
        return Str::slug("leger {$this->kelas->nama} {$this->tahunAjaran->nama} semester {$this->semester->name}").".{$ekstensi}";
    }

    /**
     * Class average of each subject's knowledge and skill score, keyed by subject id.
     *
     * @return array<int, array{p: float|null, k: float|null}>
     */
    public function rataRataMapel(): array
    {
        return $this->mataPelajaran
            ->mapWithKeys(fn (MataPelajaran $mataPelajaran): array => [$mataPelajaran->id => [
                'p' => $this->rataRata(array_column(array_column($this->baris, 'nilai'), $mataPelajaran->id), 'p'),
                'k' => $this->rataRata(array_column(array_column($this->baris, 'nilai'), $mataPelajaran->id), 'k'),
            ]])
            ->all();
    }

    /**
     * @param  array<int, array{p: int|null, k: int|null, kkm: int}>  $nilai
     */
    private function rataRata(array $nilai, string $jenis): ?float
    {
        $terisi = array_filter(array_column($nilai, $jenis), is_int(...));

        return $terisi === [] ? null : round(array_sum($terisi) / count($terisi), 2);
    }
}
