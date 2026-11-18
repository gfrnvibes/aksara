<?php

namespace App\Services\Cbt;

use App\Enums\StatusSiswa;
use App\Models\KegiatanUjian;
use App\Models\PenempatanUjian;
use App\Models\RuangUjian;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PemetaanDenahUjian
{
    /**
     * Generate exam participant numbers and assign seats across the activity's rooms.
     *
     * @param  list<int>  $kelasIds  Empty means all active students.
     */
    public function petakan(
        KegiatanUjian $kegiatan,
        array $kelasIds = [],
        string $mode = 'selang_seling',
        ?string $prefix = null,
    ): int {
        $ruangList = $kegiatan->ruang()->orderBy('urutan')->orderBy('id')->get();
        if ($ruangList->isEmpty()) {
            throw ValidationException::withMessages([
                'ruang' => 'Tambahkan minimal satu ruang ujian sebelum memetakan peserta.',
            ]);
        }

        $siswaList = Siswa::query()
            ->where('status', StatusSiswa::Aktif)
            ->when($kelasIds !== [], fn ($q) => $q->whereIn('kelas_id', $kelasIds))
            ->with('kelas')
            ->get()
            ->sortBy([
                fn (Siswa $s): int => (int) $s->kelas->tingkat,
                fn (Siswa $s): string => $s->kelas->nama,
                fn (Siswa $s): string => $s->nama_lengkap,
            ])
            ->values();

        if ($siswaList->isEmpty()) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Tidak ada siswa aktif yang ditemukan untuk dipetakan.',
            ]);
        }

        $totalKapasitas = (int) $ruangList->sum('kapasitas');
        if ($totalKapasitas < $siswaList->count()) {
            throw ValidationException::withMessages([
                'ruang' => "Kapasitas ruang ({$totalKapasitas} kursi) lebih kecil dari jumlah peserta ({$siswaList->count()} siswa). Tambahkan ruang atau perbesar kapasitas.",
            ]);
        }

        $prefixEfektif = $prefix ?? $kegiatan->prefix_nomor_peserta;
        if (blank($prefixEfektif)) {
            $tahun = $kegiatan->tahunAjaran->nama;
            $prefixEfektif = substr($tahun, 2, 2).'-';
        }

        $urutanSiswa = $mode === 'selang_seling'
            ? $this->susunSelangSeling($siswaList, $ruangList)
            : $siswaList->all();

        $nomorPerTingkat = [];
        $baris = [];
        $slotIndex = 0;

        /** @var list<array{ruang: RuangUjian, kursi: int, posisi: int}> $slots */
        $slots = [];
        foreach ($ruangList as $ruang) {
            for ($kursi = 1; $kursi <= $ruang->kapasitas; $kursi++) {
                $slots[] = [
                    'ruang' => $ruang,
                    'kursi' => $kursi,
                    'posisi' => $kursi,
                ];
            }
        }

        foreach ($urutanSiswa as $siswa) {
            $tingkat = (int) $siswa->kelas->tingkat;
            $nomorPerTingkat[$tingkat] = ($nomorPerTingkat[$tingkat] ?? 0) + 1;
            $nomorPeserta = $prefixEfektif
                .str_pad((string) $tingkat, 2, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) $nomorPerTingkat[$tingkat], 3, '0', STR_PAD_LEFT);

            $slot = $slots[$slotIndex];
            $slotIndex++;

            $baris[] = [
                'kegiatan_ujian_id' => $kegiatan->id,
                'ruang_ujian_id' => $slot['ruang']->id,
                'siswa_id' => $siswa->id,
                'kelas_id' => $siswa->kelas_id,
                'nomor_peserta' => $nomorPeserta,
                'nomor_kursi' => $slot['kursi'],
                'urutan_posisi' => $slot['posisi'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($kegiatan, $baris): void {
            $kegiatan->penempatan()->delete();
            foreach (array_chunk($baris, 200) as $chunk) {
                PenempatanUjian::query()->insert($chunk);
            }
        });

        return count($baris);
    }

    /**
     * Swap the room and seat positions of two placed students.
     */
    public function tukarKursi(PenempatanUjian $a, PenempatanUjian $b): void
    {
        if ($a->kegiatan_ujian_id !== $b->kegiatan_ujian_id) {
            return;
        }

        DB::transaction(function () use ($a, $b): void {
            $aData = [
                'ruang_ujian_id' => $a->ruang_ujian_id,
                'nomor_kursi' => $a->nomor_kursi,
                'urutan_posisi' => $a->urutan_posisi,
            ];

            $a->forceFill([
                'ruang_ujian_id' => $b->ruang_ujian_id,
                'nomor_kursi' => $b->nomor_kursi,
                'urutan_posisi' => $b->urutan_posisi,
            ])->save();

            $b->forceFill($aData)->save();
        });
    }

    /**
     * Arrange students so every 2-seat desk pairs different grade levels whenever possible.
     *
     * @param  Collection<int, Siswa>  $siswaList
     * @param  Collection<int, RuangUjian>  $ruangList
     * @return list<Siswa>
     */
    protected function susunSelangSeling(Collection $siswaList, Collection $ruangList): array
    {
        /** @var array<int, list<Siswa>> $kelompok */
        $kelompok = [];
        foreach ($siswaList as $siswa) {
            $tingkat = (int) $siswa->kelas->tingkat;
            $kelompok[$tingkat][] = $siswa;
        }

        $totalSiswa = $siswaList->count();
        $hasil = [];
        $sisa = $totalSiswa;

        foreach ($ruangList as $ruang) {
            if ($sisa <= 0) {
                break;
            }

            $kursiPerMeja = max(1, $ruang->kursi_per_meja);
            $terpakai = 0;

            while ($terpakai < $ruang->kapasitas && $sisa > 0) {
                $jumlahKursiMejaIni = min($kursiPerMeja, $ruang->kapasitas - $terpakai, $sisa);
                $tingkatDipakaiDiMeja = [];

                for ($k = 0; $k < $jumlahKursiMejaIni; $k++) {
                    $terpilih = $this->ambilTingkatTerbanyak($kelompok, $tingkatDipakaiDiMeja);
                    if ($terpilih === null) {
                        break;
                    }

                    $tingkatDipakaiDiMeja[] = $terpilih;
                    $hasil[] = array_shift($kelompok[$terpilih]);
                    if ($kelompok[$terpilih] === []) {
                        unset($kelompok[$terpilih]);
                    }
                    $terpakai++;
                    $sisa--;
                }
            }
        }

        return $hasil;
    }

    /**
     * @param  array<int, list<Siswa>>  $kelompok
     * @param  list<int>  $kecualiTingkat
     */
    protected function ambilTingkatTerbanyak(array $kelompok, array $kecualiTingkat): ?int
    {
        if ($kelompok === []) {
            return null;
        }

        $kandidat = array_filter(
            $kelompok,
            fn (array $items, int $tingkat): bool => ! in_array($tingkat, $kecualiTingkat, true),
            ARRAY_FILTER_USE_BOTH,
        );

        $sumber = $kandidat !== [] ? $kandidat : $kelompok;
        uasort($sumber, fn (array $a, array $b): int => count($b) <=> count($a));

        return array_key_first($sumber);
    }
}
