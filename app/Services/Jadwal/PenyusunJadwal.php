<?php

namespace App\Services\Jadwal;

use App\Enums\Hari;
use App\Enums\SifatPermintaanJadwal;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\PembagianMengajar;
use App\Models\PermintaanJadwal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds a timetable from the teaching assignments of its academic year.
 *
 * Each assignment's weekly periods are split into double lessons (plus one single lesson for an odd count) and
 * placed by a backtracking search that always places the lesson with the fewest free spots next. It never puts a
 * class or a teacher in two places at once and never breaks a required wish. Among valid timetables it prefers
 * ones that keep double lessons within one session (not split by a break or prayers), spread a subject over
 * several days, and follow the teachers' other wishes. Several randomised searches are run and the cheapest
 * result is kept. Periods locked by hand stay where they are.
 */
class PenyusunJadwal
{
    /**
     * What a double lesson split by a break or prayers costs; splitting is the second choice.
     */
    protected const int BIAYA_TERPOTONG = 40;

    /**
     * What each period against a teacher's non-required wish costs.
     */
    protected const int BIAYA_PERMINTAAN = 20;

    /**
     * What each further lesson of the same subject on the same day costs, to spread it over the week.
     */
    protected const int BIAYA_HARI_SAMA = 15;

    /**
     * Search steps per attempt before it gives up and starts over.
     */
    protected const int BATAS_LANGKAH = 3000;

    /**
     * Spots tried for each lesson, cheapest first.
     */
    protected const int CABANG_MAKSIMUM = 8;

    protected const float BATAS_WAKTU_DETIK = 8.0;

    protected const int PERCOBAAN_SETELAH_BERHASIL = 10;

    protected Randomizer $acak;

    /**
     * @var array<int, list<int>> Day => its lesson periods.
     */
    protected array $slot = [];

    /**
     * @var list<array{pembagian: int, kelas: int, guru: int, panjang: int}>
     */
    protected array $blok = [];

    /**
     * @var array<int, array<string, true>> Class => "day-period" => taken.
     */
    protected array $kelasSibuk = [];

    /**
     * @var array<int, array<string, true>> Teacher => "day-period" => taken.
     */
    protected array $guruSibuk = [];

    /**
     * @var array<int, array<string, true>> Assignment => "day-period" a required wish rules out.
     */
    protected array $terlarang = [];

    /**
     * @var array<int, array<string, int>> Assignment => "day-period" => cost of the non-required wishes it breaks.
     */
    protected array $biayaPermintaan = [];

    /**
     * @var array<int, array<int, int>> Assignment => day => lessons placed that day.
     */
    protected array $hariDipakai = [];

    /**
     * @var array<int, array{0: int, 1: int}> Lesson => [day, index of its first period in the day].
     */
    protected array $penempatan = [];

    protected int $langkah = 0;

    protected int $kedalamanTerjauh = -1;

    protected ?int $blokBuntu = null;

    public function __construct(protected PolaJam $pola, ?int $benih = null)
    {
        $this->acak = new Randomizer(new Mt19937($benih));
    }

    /**
     * Build the timetable and save it, keeping its locked periods. When no timetable fits, nothing is changed.
     *
     * @return array{berhasil: bool, laporan: list<array{tingkat: string, pesan: string}>}
     */
    public function susun(Jadwal $jadwal): array
    {
        if ($this->pola->kosong()) {
            return $this->gagal('Jam Pelajaran belum diatur. Isi dulu jam pelajaran tiap hari di menu Jam Pelajaran.');
        }

        /** @var Collection<int, PembagianMengajar> $pembagian */
        $pembagian = PembagianMengajar::query()
            ->with(['gtk', 'kelas', 'mataPelajaran'])
            ->where('tahun_ajaran_id', $jadwal->tahun_ajaran_id)
            ->get()
            ->keyBy('id');

        if ($pembagian->isEmpty()) {
            return $this->gagal("Belum ada pembagian mengajar untuk tahun ajaran {$jadwal->tahunAjaran->nama}. Isi dulu menu Pembagian Mengajar.");
        }

        $terkunci = $jadwal->isi()->where('is_terkunci', true)->get();
        $permintaan = PermintaanJadwal::query()->with('gtk')->where('is_aktif', true)->whereIn('gtk_id', $pembagian->pluck('gtk_id'))->get();

        $this->siapkan($pembagian, $terkunci, $permintaan);

        if (($masalah = $this->periksaKapasitas($pembagian)) !== []) {
            return ['berhasil' => false, 'laporan' => $masalah];
        }

        $hasil = $this->cariTerbaik(pecahTunggal: false);
        $dipecah = false;

        if ($hasil === null) {
            $this->susunBlok($pembagian, $terkunci, pecahTunggal: true);
            $hasil = $this->cariTerbaik(pecahTunggal: true);
            $dipecah = true;
        }

        if ($hasil === null) {
            return $this->gagal($this->pesanBuntu($pembagian));
        }

        $this->simpan($jadwal, $hasil, $pembagian);

        $laporan = [
            PemeriksaJadwal::temuan(PemeriksaJadwal::INFO, 'Jadwal disusun otomatis: '.count($hasil).' pelajaran ditempatkan'.($terkunci->isNotEmpty() ? ", {$terkunci->count()} JP terkunci dipertahankan" : '').'.'),
            ...($dipecah ? [PemeriksaJadwal::temuan(PemeriksaJadwal::PERINGATAN, 'Sebagian pelajaran tidak bisa dibuat 2 JP berturut-turut, sehingga dipasang per 1 JP. Longgarkan permintaan wajib guru agar bisa berturut-turut.')] : []),
            ...$this->catatanTerpotong($hasil, $pembagian),
            ...(new PemeriksaJadwal($this->pola))->periksa($jadwal),
        ];

        $jadwal->update(['laporan' => $laporan]);

        return ['berhasil' => true, 'laporan' => $laporan];
    }

    /**
     * @param  Collection<int, PembagianMengajar>  $pembagian
     * @param  Collection<int, IsiJadwal>  $terkunci
     * @param  Collection<int, PermintaanJadwal>  $permintaan
     */
    protected function siapkan(Collection $pembagian, Collection $terkunci, Collection $permintaan): void
    {
        $this->slot = [];

        foreach ($this->pola->hari() as $hari) {
            $this->slot[$hari->value] = $this->pola->jp($hari);
        }

        $this->terlarang = [];
        $this->biayaPermintaan = [];

        foreach ($pembagian as $tugas) {
            foreach ($permintaan->where('gtk_id', $tugas->gtk_id) as $keinginan) {
                foreach ($this->slot as $hari => $daftarJp) {
                    foreach ($daftarJp as $jp) {
                        if (! $keinginan->dilanggarOleh(Hari::from($hari), $jp, $tugas->mata_pelajaran_id)) {
                            continue;
                        }

                        if ($keinginan->sifat === SifatPermintaanJadwal::Wajib) {
                            $this->terlarang[$tugas->id]["{$hari}-{$jp}"] = true;
                        } else {
                            $this->biayaPermintaan[$tugas->id]["{$hari}-{$jp}"] = ($this->biayaPermintaan[$tugas->id]["{$hari}-{$jp}"] ?? 0) + static::BIAYA_PERMINTAAN;
                        }
                    }
                }
            }
        }

        $this->susunBlok($pembagian, $terkunci, pecahTunggal: false);
    }

    /**
     * Reset the grid to the locked periods and split what is left of each assignment into lessons.
     *
     * @param  Collection<int, PembagianMengajar>  $pembagian
     * @param  Collection<int, IsiJadwal>  $terkunci
     */
    protected function susunBlok(Collection $pembagian, Collection $terkunci, bool $pecahTunggal): void
    {
        $this->kelasSibuk = [];
        $this->guruSibuk = [];
        $this->hariDipakai = [];
        $this->blok = [];
        $sisa = $pembagian->map(fn (PembagianMengajar $tugas): int => $tugas->jp_per_minggu)->all();

        foreach ($terkunci as $sel) {
            $tugas = $pembagian->get($sel->pembagian_mengajar_id);
            $kunci = "{$sel->hari->value}-{$sel->jp_ke}";

            $this->kelasSibuk[$sel->kelas_id][$kunci] = true;

            if ($tugas !== null) {
                $this->guruSibuk[$tugas->gtk_id][$kunci] = true;
                $this->hariDipakai[$tugas->id][$sel->hari->value] = ($this->hariDipakai[$tugas->id][$sel->hari->value] ?? 0) + 1;
                $sisa[$tugas->id] = max(0, $sisa[$tugas->id] - 1);
            }
        }

        foreach ($pembagian as $tugas) {
            $jumlah = $sisa[$tugas->id];
            $panjang = $pecahTunggal ? array_fill(0, $jumlah, 1) : [...array_fill(0, intdiv($jumlah, 2), 2), ...($jumlah % 2 === 1 ? [1] : [])];

            foreach ($panjang as $jp) {
                $this->blok[] = ['pembagian' => $tugas->id, 'kelas' => $tugas->kelas_id, 'guru' => $tugas->gtk_id, 'panjang' => $jp];
            }
        }
    }

    /**
     * Catch timetables that cannot fit before searching: a class or a teacher with more periods than free spots.
     *
     * @param  Collection<int, PembagianMengajar>  $pembagian
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function periksaKapasitas(Collection $pembagian): array
    {
        $jumlahSlot = array_sum(array_map(count(...), $this->slot));
        $perKelas = [];
        $perGuru = [];

        foreach ($this->blok as $blok) {
            $perKelas[$blok['kelas']] = ($perKelas[$blok['kelas']] ?? 0) + $blok['panjang'];
            $perGuru[$blok['guru']] = ($perGuru[$blok['guru']] ?? 0) + $blok['panjang'];
        }

        $masalah = [];

        foreach ($perKelas as $kelasId => $jumlah) {
            $tersedia = $jumlahSlot - count($this->kelasSibuk[$kelasId] ?? []);

            if ($jumlah > $tersedia) {
                $kelas = $pembagian->firstWhere('kelas_id', $kelasId)?->kelas;
                $masalah[] = PemeriksaJadwal::temuan(PemeriksaJadwal::GALAT, "Kelas {$kelas?->nama}: pembagian mengajar berjumlah {$jumlah} JP, padahal jam yang tersedia hanya {$tersedia} JP. Kurangi JP pembagian mengajar atau tambah Jam Pelajaran.");
            }
        }

        foreach ($perGuru as $guruId => $jumlah) {
            $tugasGuru = $pembagian->where('gtk_id', $guruId);
            $tersedia = 0;

            foreach ($this->slot as $hari => $daftarJp) {
                foreach ($daftarJp as $jp) {
                    $kunci = "{$hari}-{$jp}";

                    if (! isset($this->guruSibuk[$guruId][$kunci]) && $tugasGuru->contains(fn (PembagianMengajar $tugas): bool => ! isset($this->terlarang[$tugas->id][$kunci]))) {
                        $tersedia++;
                    }
                }
            }

            if ($jumlah > $tersedia) {
                $masalah[] = PemeriksaJadwal::temuan(PemeriksaJadwal::GALAT, "{$tugasGuru->first()?->gtk->nama} harus mengajar {$jumlah} JP, padahal permintaan wajibnya hanya menyisakan {$tersedia} JP. Longgarkan permintaan wajib guru tersebut.");
            }
        }

        return $masalah;
    }

    /**
     * Run randomised searches and keep the cheapest timetable found, or null when none fits.
     *
     * @return array<int, array{0: int, 1: int}>|null
     */
    protected function cariTerbaik(bool $pecahTunggal): ?array
    {
        $batasWaktu = microtime(true) + ($pecahTunggal ? static::BATAS_WAKTU_DETIK / 2 : static::BATAS_WAKTU_DETIK);
        $terbaik = null;
        $biayaTerbaik = PHP_INT_MAX;
        $berhasil = 0;
        $this->kedalamanTerjauh = -1;
        $this->blokBuntu = null;

        while (microtime(true) < $batasWaktu && $berhasil < static::PERCOBAAN_SETELAH_BERHASIL) {
            $this->langkah = 0;
            $this->penempatan = [];

            if ($this->cari(array_keys($this->blok))) {
                $berhasil++;
                $this->perhalus();
                $biaya = $this->biayaTotal();

                if ($biaya < $biayaTerbaik) {
                    $terbaik = $this->penempatan;
                    $biayaTerbaik = $biaya;
                }

                if ($biaya === 0) {
                    break;
                }
            }

            foreach ($this->penempatan as $blok => [$hari, $mulai]) {
                $this->lepas($blok, $hari, $mulai);
            }
        }

        return $terbaik;
    }

    /**
     * Place the remaining lessons, most constrained first, backtracking on a dead end.
     *
     * @param  array<int, int>  $sisa
     */
    protected function cari(array $sisa): bool
    {
        if ($sisa === []) {
            return true;
        }

        if (++$this->langkah > static::BATAS_LANGKAH) {
            return false;
        }

        $dipilih = null;
        $kandidatDipilih = [];

        foreach ($sisa as $posisi => $blok) {
            $kandidat = $this->kandidat($blok, acak: true);

            if ($kandidat === []) {
                if (count($this->penempatan) > $this->kedalamanTerjauh) {
                    $this->kedalamanTerjauh = count($this->penempatan);
                    $this->blokBuntu = $blok;
                }

                return false;
            }

            if ($dipilih === null || count($kandidat) < count($kandidatDipilih)) {
                $dipilih = $posisi;
                $kandidatDipilih = $kandidat;
            }
        }

        $blok = $sisa[$dipilih];
        unset($sisa[$dipilih]);

        foreach (array_slice($kandidatDipilih, 0, static::CABANG_MAKSIMUM) as [$hari, $mulai]) {
            $this->pasang($blok, $hari, $mulai);

            if ($this->cari($sisa)) {
                return true;
            }

            $this->lepas($blok, $hari, $mulai);

            if ($this->langkah > static::BATAS_LANGKAH) {
                return false;
            }
        }

        return false;
    }

    /**
     * Where the lesson can go, cheapest first: [day, index of first period, cost].
     *
     * @return list<array{0: int, 1: int, 2: int}>
     */
    protected function kandidat(int $blok, bool $acak): array
    {
        ['pembagian' => $tugas, 'kelas' => $kelas, 'guru' => $guru, 'panjang' => $panjang] = $this->blok[$blok];
        $kandidat = [];

        foreach ($this->slot as $hari => $daftarJp) {
            for ($mulai = 0; $mulai + $panjang <= count($daftarJp); $mulai++) {
                $biaya = $this->biayaTempat($tugas, $kelas, $guru, $panjang, $hari, $mulai);

                if ($biaya !== null) {
                    $kandidat[] = [$hari, $mulai, $biaya + ($acak ? $this->acak->getInt(0, 4) : 0)];
                }
            }
        }

        usort($kandidat, fn (array $a, array $b): int => $a[2] <=> $b[2]);

        return $kandidat;
    }

    /**
     * The cost of putting the lesson there, or null when the class or teacher is taken or a required wish rules
     * it out.
     */
    protected function biayaTempat(int $tugas, int $kelas, int $guru, int $panjang, int $hari, int $mulai): ?int
    {
        $biaya = 0;

        for ($i = 0; $i < $panjang; $i++) {
            $kunci = $hari.'-'.$this->slot[$hari][$mulai + $i];

            if (isset($this->kelasSibuk[$kelas][$kunci]) || isset($this->guruSibuk[$guru][$kunci]) || isset($this->terlarang[$tugas][$kunci])) {
                return null;
            }

            $biaya += $this->biayaPermintaan[$tugas][$kunci] ?? 0;
        }

        if ($panjang > 1 && ! $this->pola->satuSesi(Hari::from($hari), $this->slot[$hari][$mulai], $this->slot[$hari][$mulai + $panjang - 1])) {
            $biaya += static::BIAYA_TERPOTONG;
        }

        return $biaya + ($this->hariDipakai[$tugas][$hari] ?? 0) * static::BIAYA_HARI_SAMA;
    }

    protected function pasang(int $blok, int $hari, int $mulai): void
    {
        $this->tandai($blok, $hari, $mulai, true);
        $this->penempatan[$blok] = [$hari, $mulai];
    }

    protected function lepas(int $blok, int $hari, int $mulai): void
    {
        $this->tandai($blok, $hari, $mulai, false);
        unset($this->penempatan[$blok]);
    }

    protected function tandai(int $blok, int $hari, int $mulai, bool $sibuk): void
    {
        ['pembagian' => $tugas, 'kelas' => $kelas, 'guru' => $guru, 'panjang' => $panjang] = $this->blok[$blok];

        for ($i = 0; $i < $panjang; $i++) {
            $kunci = $hari.'-'.$this->slot[$hari][$mulai + $i];

            if ($sibuk) {
                $this->kelasSibuk[$kelas][$kunci] = true;
                $this->guruSibuk[$guru][$kunci] = true;
            } else {
                unset($this->kelasSibuk[$kelas][$kunci], $this->guruSibuk[$guru][$kunci]);
            }
        }

        $this->hariDipakai[$tugas][$hari] = ($this->hariDipakai[$tugas][$hari] ?? 0) + ($sibuk ? 1 : -1);
    }

    /**
     * Move single lessons to cheaper free spots until none can be improved.
     */
    protected function perhalus(): void
    {
        for ($putaran = 0; $putaran < 3; $putaran++) {
            $membaik = false;

            foreach ($this->penempatan as $blok => [$hari, $mulai]) {
                $this->lepas($blok, $hari, $mulai);
                $sekarang = $this->biayaBlok($blok, $hari, $mulai);
                $terbaik = $this->kandidat($blok, acak: false)[0] ?? null;

                if ($terbaik !== null && $sekarang !== null && $terbaik[2] < $sekarang) {
                    $this->pasang($blok, $terbaik[0], $terbaik[1]);
                    $membaik = true;
                } else {
                    $this->pasang($blok, $hari, $mulai);
                }
            }

            if (! $membaik) {
                return;
            }
        }
    }

    protected function biayaBlok(int $blok, int $hari, int $mulai): ?int
    {
        ['pembagian' => $tugas, 'kelas' => $kelas, 'guru' => $guru, 'panjang' => $panjang] = $this->blok[$blok];

        return $this->biayaTempat($tugas, $kelas, $guru, $panjang, $hari, $mulai);
    }

    protected function biayaTotal(): int
    {
        $total = 0;

        foreach ($this->penempatan as $blok => [$hari, $mulai]) {
            $this->lepas($blok, $hari, $mulai);
            $total += (int) $this->biayaBlok($blok, $hari, $mulai);
            $this->pasang($blok, $hari, $mulai);
        }

        return $total;
    }

    /**
     * Replace the unlocked periods of the timetable with the lessons found.
     *
     * @param  array<int, array{0: int, 1: int}>  $hasil
     * @param  Collection<int, PembagianMengajar>  $pembagian
     */
    protected function simpan(Jadwal $jadwal, array $hasil, Collection $pembagian): void
    {
        DB::transaction(function () use ($jadwal, $hasil, $pembagian): void {
            $jadwal->isi()->where('is_terkunci', false)->delete();
            $sekarang = now();
            $baris = [];

            foreach ($hasil as $blok => [$hari, $mulai]) {
                $tugas = $pembagian->get($this->blok[$blok]['pembagian']);

                for ($i = 0; $i < $this->blok[$blok]['panjang']; $i++) {
                    $baris[] = [
                        'jadwal_id' => $jadwal->id,
                        'kelas_id' => $tugas->kelas_id,
                        'pembagian_mengajar_id' => $tugas->id,
                        'hari' => $hari,
                        'jp_ke' => $this->slot[$hari][$mulai + $i],
                        'is_terkunci' => false,
                        'created_at' => $sekarang,
                        'updated_at' => $sekarang,
                    ];
                }
            }

            foreach (array_chunk($baris, 200) as $potongan) {
                IsiJadwal::query()->insert($potongan);
            }
        });
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $hasil
     * @param  Collection<int, PembagianMengajar>  $pembagian
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function catatanTerpotong(array $hasil, Collection $pembagian): array
    {
        $catatan = [];

        foreach ($hasil as $blok => [$hari, $mulai]) {
            $panjang = $this->blok[$blok]['panjang'];
            $awal = $this->slot[$hari][$mulai];
            $akhir = $this->slot[$hari][$mulai + $panjang - 1];

            if ($panjang > 1 && ! $this->pola->satuSesi(Hari::from($hari), $awal, $akhir)) {
                $tugas = $pembagian->get($this->blok[$blok]['pembagian']);
                $catatan[] = PemeriksaJadwal::temuan(PemeriksaJadwal::PERINGATAN, "{$tugas->mataPelajaran->nama} kelas {$tugas->kelas->nama}: ".Hari::from($hari)->getLabel()." JP {$awal}–{$akhir} terpotong istirahat atau shalat.");
            }
        }

        return $catatan;
    }

    /**
     * @param  Collection<int, PembagianMengajar>  $pembagian
     */
    protected function pesanBuntu(Collection $pembagian): string
    {
        $tugas = $this->blokBuntu === null ? null : $pembagian->get($this->blok[$this->blokBuntu]['pembagian'] ?? 0);

        return $tugas === null
            ? 'Jadwal tidak dapat disusun dengan permintaan wajib dan sel terkunci yang ada. Longgarkan sebagian permintaan wajib guru lalu coba lagi.'
            : "Jadwal tidak dapat disusun: {$tugas->mataPelajaran->nama} kelas {$tugas->kelas->nama} ({$tugas->gtk->nama}) tidak mendapat tempat. Longgarkan permintaan wajib {$tugas->gtk->nama} atau guru lain di kelas {$tugas->kelas->nama}, atau lepas kunci sebagian sel, lalu coba lagi.";
    }

    /**
     * @return array{berhasil: false, laporan: list<array{tingkat: string, pesan: string}>}
     */
    protected function gagal(string $pesan): array
    {
        return ['berhasil' => false, 'laporan' => [PemeriksaJadwal::temuan(PemeriksaJadwal::GALAT, $pesan)]];
    }
}
