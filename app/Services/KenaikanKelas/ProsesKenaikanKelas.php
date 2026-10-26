<?php

namespace App\Services\KenaikanKelas;

use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Enums\TindakanKenaikan;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Moves the students of one class into the next academic year: promoted students go up a grade, retained
 * students repeat it, and final-grade graduates become alumni. The class of the year being closed is kept in
 * the class history.
 */
final class ProsesKenaikanKelas
{
    /**
     * @param  array<int, TindakanKenaikan>  $tindakanPerSiswa  keyed by student id
     * @return array<string, int> number of students per action, keyed by the action's value
     *
     * @throws DomainException when the years or classes do not fit the requested actions
     */
    public function proses(
        TahunAjaran $tahunAsal,
        TahunAjaran $tahunTujuan,
        Kelas $kelasAsal,
        ?Kelas $kelasNaik,
        ?Kelas $kelasTinggal,
        array $tindakanPerSiswa,
    ): array {
        $this->pastikanSesuai($tahunAsal, $tahunTujuan, $kelasAsal, $kelasNaik, $kelasTinggal, $tindakanPerSiswa);

        $daftarSiswa = Siswa::query()
            ->whereKey(array_keys($tindakanPerSiswa))
            ->where('kelas_id', $kelasAsal->id)
            ->where('status', StatusSiswa::Aktif)
            ->get();

        $tanggalKeputusan = Rapor::query()
            ->whereIn('siswa_id', $daftarSiswa->modelKeys())
            ->where('tahun_ajaran_id', $tahunAsal->id)
            ->where('semester', Semester::Genap)
            ->pluck('tanggal_keputusan', 'siswa_id');

        $jumlah = array_fill_keys(array_column(TindakanKenaikan::cases(), 'value'), 0);

        DB::transaction(function () use ($daftarSiswa, $tindakanPerSiswa, $tahunAsal, $tahunTujuan, $kelasAsal, $kelasNaik, $kelasTinggal, $tanggalKeputusan, &$jumlah): void {
            foreach ($daftarSiswa as $siswa) {
                $tindakan = $tindakanPerSiswa[$siswa->id];

                if ($tindakan === TindakanKenaikan::Lewati) {
                    $jumlah[$tindakan->value]++;

                    continue;
                }

                RiwayatKelas::query()->firstOrCreate(
                    ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $tahunAsal->id],
                    ['kelas_id' => $kelasAsal->id],
                );

                match ($tindakan) {
                    TindakanKenaikan::Naik => $this->pindahkan($siswa, $tahunTujuan, $kelasNaik),
                    TindakanKenaikan::TinggalDiKelas => $this->pindahkan($siswa, $tahunTujuan, $kelasTinggal ?? $kelasAsal),
                    TindakanKenaikan::Lulus => $siswa->update([
                        'status' => StatusSiswa::Lulus,
                        'kelas_id' => null,
                        'tanggal_tamat' => $siswa->getAttribute('tanggal_tamat') ?? $tanggalKeputusan->get($siswa->id) ?? now()->toDateString(),
                    ]),
                };

                $jumlah[$tindakan->value]++;
            }
        });

        return $jumlah;
    }

    /**
     * The class history row of the new year is written first, so the student's saved hook updates that row.
     */
    private function pindahkan(Siswa $siswa, TahunAjaran $tahunTujuan, ?Kelas $kelas): void
    {
        RiwayatKelas::query()->updateOrCreate(
            ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $tahunTujuan->id],
            ['kelas_id' => $kelas?->id],
        );

        $siswa->update(['kelas_id' => $kelas?->id]);
    }

    /**
     * @param  array<int, TindakanKenaikan>  $tindakanPerSiswa
     *
     * @throws DomainException
     */
    private function pastikanSesuai(
        TahunAjaran $tahunAsal,
        TahunAjaran $tahunTujuan,
        Kelas $kelasAsal,
        ?Kelas $kelasNaik,
        ?Kelas $kelasTinggal,
        array $tindakanPerSiswa,
    ): void {
        if (strcmp($tahunTujuan->nama, $tahunAsal->nama) <= 0) {
            throw new DomainException("Tahun ajaran tujuan harus sesudah {$tahunAsal->nama}.");
        }

        $tindakan = collect($tindakanPerSiswa);
        $tingkatAkhir = max(array_keys(KelasResource::getTingkatOptions()));

        if ($tindakan->contains(TindakanKenaikan::Naik)) {
            if ($kelasAsal->tingkat >= $tingkatAkhir) {
                throw new DomainException("Siswa kelas {$kelasAsal->nama} tidak bisa naik kelas; pilih Lulus atau Tinggal di Kelas.");
            }

            if ($kelasNaik === null || $kelasNaik->tingkat !== $kelasAsal->tingkat + 1) {
                throw new DomainException('Pilih kelas tujuan satu tingkat di atas kelas asal untuk siswa yang naik kelas.');
            }
        }

        if ($tindakan->contains(TindakanKenaikan::TinggalDiKelas) && $kelasTinggal && $kelasTinggal->tingkat !== $kelasAsal->tingkat) {
            throw new DomainException('Kelas untuk siswa yang tinggal di kelas harus setingkat dengan kelas asal.');
        }

        if ($tindakan->contains(TindakanKenaikan::Lulus) && $kelasAsal->tingkat < $tingkatAkhir) {
            throw new DomainException('Hanya siswa tingkat akhir yang bisa diluluskan.');
        }
    }
}
