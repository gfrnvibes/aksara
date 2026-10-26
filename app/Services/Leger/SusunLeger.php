<?php

namespace App\Services\Leger;

use App\Enums\Semester;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;

/**
 * Builds the grade recap of a class: the members of the class that year, their report-card grades of the
 * semester, and a rank by average where equal averages share a rank (1, 2, 2, 4).
 */
final class SusunLeger
{
    public function untuk(Kelas $kelas, TahunAjaran $tahunAjaran, Semester $semester): Leger
    {
        $daftarSiswa = Siswa::query()
            ->anggotaKelas($kelas->id, $tahunAjaran->id)
            ->orderBy('nama_lengkap')
            ->get();

        $nilaiPerSiswa = Rapor::query()
            ->whereIn('siswa_id', $daftarSiswa->modelKeys())
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester', $semester)
            ->with('nilaiRapor')
            ->get()
            ->mapWithKeys(fn (Rapor $rapor): array => [$rapor->siswa_id => $rapor->nilaiRapor->keyBy('mata_pelajaran_id')]);

        $mapelTerpakai = $nilaiPerSiswa->flatten()->pluck('mata_pelajaran_id')->unique()->all();

        $mataPelajaran = MataPelajaran::query()
            ->where(fn ($query) => $query->where('is_aktif', true)->orWhereIn('id', $mapelTerpakai))
            ->orderBy('kelompok')
            ->orderBy('urutan')
            ->get();

        $baris = $daftarSiswa
            ->map(function (Siswa $siswa) use ($nilaiPerSiswa, $mataPelajaran): array {
                $nilaiSiswa = $nilaiPerSiswa->get($siswa->id, collect());

                $nilai = $mataPelajaran
                    ->mapWithKeys(function (MataPelajaran $mapel) use ($nilaiSiswa): array {
                        /** @var NilaiRapor|null $nilaiMapel */
                        $nilaiMapel = $nilaiSiswa->get($mapel->id);

                        return [$mapel->id => [
                            'p' => $nilaiMapel?->nilai_pengetahuan,
                            'k' => $nilaiMapel?->nilai_keterampilan,
                            'kkm' => $nilaiMapel->kkm ?? $mapel->kkm,
                        ]];
                    })
                    ->all();

                $terisi = array_filter([...array_column($nilai, 'p'), ...array_column($nilai, 'k')], is_int(...));

                return [
                    'siswa' => $siswa,
                    'nilai' => $nilai,
                    'jumlah' => array_sum($terisi),
                    'rata_rata' => $terisi === [] ? null : round(array_sum($terisi) / count($terisi), 2),
                    'peringkat' => null,
                ];
            })
            ->all();

        return new Leger($kelas, $tahunAjaran, $semester, $mataPelajaran, $this->beriPeringkat(array_values($baris)));
    }

    /**
     * @param  list<array{siswa: Siswa, nilai: array<int, array{p: int|null, k: int|null, kkm: int}>, jumlah: int, rata_rata: float|null, peringkat: int|null}>  $baris
     * @return list<array{siswa: Siswa, nilai: array<int, array{p: int|null, k: int|null, kkm: int}>, jumlah: int, rata_rata: float|null, peringkat: int|null}>
     */
    private function beriPeringkat(array $baris): array
    {
        $rataRata = array_filter(array_column($baris, 'rata_rata'), is_float(...));

        foreach ($baris as $urutan => $siswa) {
            if ($siswa['rata_rata'] !== null) {
                $baris[$urutan]['peringkat'] = 1 + count(array_filter($rataRata, fn (float $lain): bool => $lain > $siswa['rata_rata']));
            }
        }

        return $baris;
    }
}
