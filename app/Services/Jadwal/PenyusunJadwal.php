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
 * Each class's assignments are first split into lessons of three, two or one periods that tile its sessions (the
 * runs of periods between breaks and prayers) the way the madrasah lays out a timetable by hand: a session of four
 * takes two double lessons, a session of three a triple lesson, and a lone period such as the one before the
 * midday prayer a single lesson. A subject meets for at most three periods at a time, so four periods a week are
 * 2 + 2 and five are 3 + 2; single lessons are made only where the sessions need them.
 *
 * The lessons are then placed by a backtracking search that always places the lesson with the fewest free spots
 * next. It never puts a class or a teacher in two places at once and never breaks a required wish. Among valid
 * timetables it prefers ones that fill each session neatly, keep a lesson within one session, meet a subject on
 * different days and follow the teachers' other wishes. Several randomised searches are run and the cheapest
 * result is kept. When none fits, the lesson that got stuck is split and the search tried again. Periods locked by
 * hand stay where they are.
 */
class PenyusunJadwal
{
    /**
     * What a lesson split by a break or prayers costs; splitting is the second choice.
     */
    protected const int BIAYA_TERPOTONG = 40;

    /**
     * What a lesson costs for each odd gap it leaves in its session, which only a single lesson can fill.
     */
    protected const int BIAYA_CELAH = 10;

    /**
     * What a lesson costs for leaving the odd period of an odd session at its start rather than at its end.
     */
    protected const int BIAYA_CELAH_AWAL = 3;

    /**
     * What each period against a teacher's non-required wish costs.
     */
    protected const int BIAYA_PERMINTAAN = 20;

    /**
     * What each further lesson of the same subject on the same day costs, to meet it on different days.
     */
    protected const int BIAYA_HARI_SAMA = 15;

    /**
     * What a lesson right after another of the same subject costs, which would make one meeting too long.
     */
    protected const int BIAYA_BERSAMBUNG = 30;

    /**
     * Search steps per attempt before it gives up and starts over.
     */
    protected const int BATAS_LANGKAH = 3000;

    /**
     * Spots tried for each lesson, cheapest first.
     */
    protected const int CABANG_MAKSIMUM = 8;

    /**
     * Time for the first search, for each search after a lesson is split, and for all of them together.
     */
    protected const float BATAS_WAKTU_DETIK = 8.0;

    protected const float BATAS_WAKTU_ULANG_DETIK = 3.0;

    protected const float BATAS_WAKTU_TOTAL_DETIK = 25.0;

    protected const int PERCOBAAN_SETELAH_BERHASIL = 10;

    protected Randomizer $acak;

    /**
     * @var array<int, list<int>> Day => its lesson periods.
     */
    protected array $slot = [];

    /**
     * @var array<int, array<int, int>> Day => index of each period => index of the first period of its session.
     */
    protected array $awalSesi = [];

    /**
     * @var array<int, array<int, int>> Day => index of a session's first period => periods in the session.
     */
    protected array $panjangSesi = [];

    /**
     * @var list<array{pembagian: int, kelas: int, guru: int, panjang: int}>
     */
    protected array $blok = [];

    /**
     * @var array<int, array<string, int>> Class => "day-period" => the assignment taught then (0 when unknown).
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
     * @var array<int, int> Teacher => single lessons planned for them across classes.
     */
    protected array $tunggalGuru = [];

    /**
     * @var array<int, array{0: int, 1: int}> Lesson => [day, index of its first period in the day].
     */
    protected array $penempatan = [];

    /**
     * @var array<int, int> Lesson => how often a search got stuck on it.
     */
    protected array $bobot = [];

    /**
     * @var array<int, true> Assignments with a lesson split because it did not fit.
     */
    protected array $dipecah = [];

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

        if ((int) ini_get('max_execution_time') > 0) {
            set_time_limit((int) static::BATAS_WAKTU_TOTAL_DETIK + 30);
        }

        $batasTotal = microtime(true) + static::BATAS_WAKTU_TOTAL_DETIK;
        $hasil = $this->cariTerbaik(min($batasTotal, microtime(true) + static::BATAS_WAKTU_DETIK));

        while ($hasil === null && microtime(true) < $batasTotal && $this->pecahBuntu()) {
            $hasil = $this->cariTerbaik(min($batasTotal, microtime(true) + static::BATAS_WAKTU_ULANG_DETIK));
        }

        if ($hasil === null) {
            return $this->gagal($this->pesanBuntu($pembagian));
        }

        $this->simpan($jadwal, $hasil, $pembagian);

        $laporan = [
            PemeriksaJadwal::temuan(PemeriksaJadwal::INFO, 'Jadwal disusun otomatis: '.count($hasil).' pertemuan ditempatkan'.($terkunci->isNotEmpty() ? ", {$terkunci->count()} JP terkunci dipertahankan" : '').'.'),
            ...$this->catatanDipecah($pembagian),
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
        $this->awalSesi = [];
        $this->panjangSesi = [];
        $this->dipecah = [];
        $this->bobot = [];

        foreach ($this->pola->hari() as $hari) {
            $this->slot[$hari->value] = $daftarJp = $this->pola->jp($hari);
            $awal = 0;

            foreach ($daftarJp as $i => $jp) {
                if ($i > 0 && ! $this->pola->satuSesi($hari, $daftarJp[$i - 1], $jp)) {
                    $awal = $i;
                }

                $this->awalSesi[$hari->value][$i] = $awal;
                $this->panjangSesi[$hari->value][$awal] = ($this->panjangSesi[$hari->value][$awal] ?? 0) + 1;
            }
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

        $this->susunBlok($pembagian, $terkunci);
    }

    /**
     * Mark the locked periods on the grid and split what is left of each assignment into lessons.
     *
     * @param  Collection<int, PembagianMengajar>  $pembagian
     * @param  Collection<int, IsiJadwal>  $terkunci
     */
    protected function susunBlok(Collection $pembagian, Collection $terkunci): void
    {
        $this->kelasSibuk = [];
        $this->guruSibuk = [];
        $this->hariDipakai = [];
        $this->tunggalGuru = [];
        $this->blok = [];
        $sisa = $pembagian->map(fn (PembagianMengajar $tugas): int => $tugas->jp_per_minggu)->all();

        foreach ($terkunci as $sel) {
            $tugas = $pembagian->get($sel->pembagian_mengajar_id);
            $kunci = "{$sel->hari->value}-{$sel->jp_ke}";

            $this->kelasSibuk[$sel->kelas_id][$kunci] = $tugas->id ?? 0;

            if ($tugas !== null) {
                $this->guruSibuk[$tugas->gtk_id][$kunci] = true;
                $this->hariDipakai[$tugas->id][$sel->hari->value] = ($this->hariDipakai[$tugas->id][$sel->hari->value] ?? 0) + 1;
                $sisa[$tugas->id] = max(0, $sisa[$tugas->id] - 1);
            }
        }

        foreach ($pembagian->groupBy('kelas_id') as $kelasId => $tugasKelas) {
            foreach ($this->rencanakan($kelasId, $tugasKelas, $sisa) as $tugasId => $daftarPanjang) {
                $tugas = $pembagian->get($tugasId);

                foreach ($daftarPanjang as $panjang) {
                    $this->blok[] = ['pembagian' => $tugas->id, 'kelas' => $tugas->kelas_id, 'guru' => $tugas->gtk_id, 'panjang' => $panjang];
                }
            }
        }
    }

    /**
     * Split the class's assignments into lessons that can tile its free sessions.
     *
     * Every session of odd length needs an odd lesson (three or one periods) unless one of its periods is left
     * free, so the class gets at least that many odd lessons. Odd assignments give one each, as a triple lesson
     * while there are sessions to hold it (5 = 3 + 2) and otherwise a single one (5 = 2 + 2 + 1). More are made
     * from even assignments: 6 = 3 + 3 or 4 = 3 + 1 while triple lessons fit, and after that by splitting a
     * double lesson into two singles (see {@see pilihUntukTunggal()}).
     *
     * @param  Collection<int, PembagianMengajar>  $tugasKelas
     * @param  array<int, int>  $sisa  Assignment => periods left to place.
     * @return array<int, list<int>> Assignment => length of each of its lessons.
     */
    protected function rencanakan(int $kelasId, Collection $tugasKelas, array $sisa): array
    {
        $runs = $this->ruangKosong($kelasId);
        $ruang = array_map(count(...), $runs);
        $jamTunggal = array_merge(...array_values(array_filter($runs, fn (array $run): bool => count($run) === 1)));
        $sesiGanjil = count(array_filter($ruang, fn (int $panjang): bool => $panjang % 2 === 1));
        $tempatTiga = array_sum(array_map(fn (int $panjang): int => match (true) {
            $panjang % 2 === 1 && $panjang >= 3 => 1,
            $panjang % 2 === 0 && $panjang >= 6 => 2,
            default => 0,
        }, $ruang));
        $longgar = max(0, array_sum($ruang) - $tugasKelas->sum(fn (PembagianMengajar $tugas): int => $sisa[$tugas->id]));

        $guru = $tugasKelas->pluck('gtk_id', 'id')->all();
        $dua = [];
        $tiga = [];
        $satu = [];

        foreach ($tugasKelas->sortByDesc(fn (PembagianMengajar $tugas): int => $sisa[$tugas->id]) as $tugas) {
            if ($sisa[$tugas->id] > 0) {
                $dua[$tugas->id] = intdiv($sisa[$tugas->id], 2);
                $tiga[$tugas->id] = 0;
                $satu[$tugas->id] = 0;
            }
        }

        $ganjil = 0;

        foreach (array_keys($dua) as $id) {
            if ($sisa[$id] % 2 === 0) {
                continue;
            }

            $ganjil++;

            if ($dua[$id] > 0 && array_sum($tiga) < $tempatTiga) {
                $dua[$id]--;
                $tiga[$id]++;
            } else {
                $satu[$id]++;
            }
        }

        $kurang = max(0, $sesiGanjil - $longgar) - $ganjil;
        $kurang += max(0, $kurang) % 2;
        $utuh = fn (int $minimalDua): ?int => array_key_first(array_filter(
            $dua,
            fn (int $jumlah, int $id): bool => $tiga[$id] + $satu[$id] === 0 && $jumlah >= $minimalDua,
            ARRAY_FILTER_USE_BOTH,
        ));

        while ($kurang > 0) {
            if ($tempatTiga - array_sum($tiga) >= 2 && ($id = $utuh(3)) !== null) {
                $dua[$id] -= 3;
                $tiga[$id] += 2;
            } elseif ($tempatTiga - array_sum($tiga) >= 1 && ($id = $utuh(2)) !== null) {
                $dua[$id] -= 2;
                $tiga[$id]++;
                $satu[$id]++;
            } elseif (($id = $this->pilihUntukTunggal($dua, $tiga, $satu, $guru, $jamTunggal)) !== null) {
                $dua[$id]--;
                $satu[$id] += 2;
            } else {
                break;
            }

            $kurang -= 2;
        }

        $rencana = [];

        foreach ($dua as $id => $jumlah) {
            $this->tunggalGuru[$guru[$id]] = ($this->tunggalGuru[$guru[$id]] ?? 0) + $satu[$id];
            $rencana[$id] = [...array_fill(0, $tiga[$id], 3), ...array_fill(0, $jumlah, 2), ...array_fill(0, $satu[$id], 1)];
        }

        return $rencana;
    }

    /**
     * The assignment whose double lesson is split into two singles. Single lessons mostly land on the lone periods
     * (like the one before the midday prayer), which fall at the same time in every class, so a teacher with single
     * lessons in two classes would have to be in both at once, and a teacher whose required wishes rule those
     * periods out could not take them at all. Singles therefore go to an assignment that keeps a longer lesson as
     * well, whose teacher may teach on enough of the lone periods and has no singles in other classes, kept
     * together in one assignment, the one with the most periods.
     *
     * @param  array<int, int>  $dua  Assignment => its double lessons, in order of periods, most first.
     * @param  array<int, int>  $tiga  Assignment => its triple lessons.
     * @param  array<int, int>  $satu  Assignment => its single lessons.
     * @param  array<int, int>  $guru  Assignment => teacher.
     * @param  list<string>  $jamTunggal  The class's lone free periods, as "day-period".
     */
    protected function pilihUntukTunggal(array $dua, array $tiga, array $satu, array $guru, array $jamTunggal): ?int
    {
        $dipilih = null;
        $kunciDipilih = null;

        foreach ($dua as $id => $jumlah) {
            $bisa = count(array_filter($jamTunggal, fn (string $jam): bool => ! isset($this->terlarang[$id][$jam])));
            $kunci = [$jumlah + $tiga[$id] >= 2 ? 0 : 1, $bisa < $satu[$id] + 2 ? 1 : 0, $this->tunggalGuru[$guru[$id]] ?? 0, $satu[$id] > 0 ? 0 : 1];

            if ($jumlah > 0 && ($kunciDipilih === null || $kunci < $kunciDipilih)) {
                $dipilih = $id;
                $kunciDipilih = $kunci;
            }
        }

        return $dipilih;
    }

    /**
     * The runs of free periods the class has, each within one session, as "day-period" keys.
     *
     * @return list<list<string>>
     */
    protected function ruangKosong(int $kelasId): array
    {
        $ruang = [];

        foreach ($this->slot as $hari => $daftarJp) {
            $run = [];

            foreach ($daftarJp as $i => $jp) {
                $kunci = "{$hari}-{$jp}";

                if ($this->awalSesi[$hari][$i] === $i || isset($this->kelasSibuk[$kelasId][$kunci])) {
                    $ruang[] = $run;
                    $run = [];
                }

                if (! isset($this->kelasSibuk[$kelasId][$kunci])) {
                    $run[] = $kunci;
                }
            }

            $ruang[] = $run;
        }

        return array_values(array_filter($ruang));
    }

    /**
     * Split the lesson the last search got stuck on (or, when that is a single one, a longer lesson of the same
     * teacher or class) in two, so the next search has more room. False when nothing is left to split.
     */
    protected function pecahBuntu(): bool
    {
        if ($this->blokBuntu === null) {
            return false;
        }

        $buntu = $this->blok[$this->blokBuntu];
        $dipilih = $buntu['panjang'] > 1 ? $this->blokBuntu : null;

        foreach (['guru', 'kelas'] as $kunci) {
            $dipilih ??= array_key_first(array_filter(
                $this->blok,
                fn (array $blok): bool => $blok[$kunci] === $buntu[$kunci] && $blok['panjang'] > 1,
            ));
        }

        if ($dipilih === null) {
            return false;
        }

        $blok = $this->blok[$dipilih];
        $this->blok[$dipilih] = [...$blok, 'panjang' => $blok['panjang'] - 1];
        $this->blok[] = [...$blok, 'panjang' => 1];
        $this->dipecah[$blok['pembagian']] = true;

        return true;
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
    protected function cariTerbaik(float $batasWaktu): ?array
    {
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
     * Place the remaining lessons, most constrained first, backtracking on a dead end. A lesson is more
     * constrained the fewer spots it has left and the more often it has been the one with none left, so lessons
     * that keep getting stuck are placed earlier in later attempts.
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
        $skorDipilih = 0.0;

        foreach ($sisa as $posisi => $blok) {
            $kandidat = $this->kandidat($blok, acak: true);

            if ($kandidat === []) {
                $this->bobot[$blok] = ($this->bobot[$blok] ?? 0) + 1;

                if (count($this->penempatan) > $this->kedalamanTerjauh) {
                    $this->kedalamanTerjauh = count($this->penempatan);
                    $this->blokBuntu = $blok;
                }

                return false;
            }

            $skor = count($kandidat) / (1 + ($this->bobot[$blok] ?? 0));

            if ($dipilih === null || $skor < $skorDipilih) {
                $dipilih = $posisi;
                $kandidatDipilih = $kandidat;
                $skorDipilih = $skor;
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

        return $biaya
            + $this->biayaLetak($hari, $mulai, $panjang)
            + $this->biayaBersambung($tugas, $kelas, $hari, $mulai, $panjang)
            + ($this->hariDipakai[$tugas][$hari] ?? 0) * static::BIAYA_HARI_SAMA;
    }

    /**
     * How well the lesson fits its session: nothing when what is left on either side can still be filled with
     * double lessons (or, in a session of odd length, leaves the one odd period at its end), more when it leaves
     * gaps only single lessons can fill or runs across a break.
     */
    protected function biayaLetak(int $hari, int $mulai, int $panjang): int
    {
        $awal = $this->awalSesi[$hari][$mulai];

        if ($this->awalSesi[$hari][$mulai + $panjang - 1] !== $awal) {
            return static::BIAYA_TERPOTONG;
        }

        $kiri = ($mulai - $awal) % 2;
        $kanan = ($this->panjangSesi[$hari][$awal] - ($mulai - $awal) - $panjang) % 2;

        if ($this->panjangSesi[$hari][$awal] % 2 === 0 || ($kiri === 1 && $kanan === 1)) {
            return ($kiri + $kanan) * static::BIAYA_CELAH;
        }

        return $kiri === 1 || ($panjang === 1 && $mulai - $awal + 1 < $this->panjangSesi[$hari][$awal]) ? static::BIAYA_CELAH_AWAL : 0;
    }

    /**
     * Whether the lesson would run straight on from another lesson of the same assignment in the same session.
     */
    protected function biayaBersambung(int $tugas, int $kelas, int $hari, int $mulai, int $panjang): int
    {
        $awal = $this->awalSesi[$hari][$mulai];
        $biaya = 0;

        foreach ([$mulai - 1, $mulai + $panjang] as $i) {
            if (($this->awalSesi[$hari][$i] ?? null) === $awal && ($this->kelasSibuk[$kelas][$hari.'-'.$this->slot[$hari][$i]] ?? null) === $tugas) {
                $biaya += static::BIAYA_BERSAMBUNG;
            }
        }

        return $biaya;
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
                $this->kelasSibuk[$kelas][$kunci] = $tugas;
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
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function catatanDipecah(Collection $pembagian): array
    {
        return array_map(function (int $id) use ($pembagian): array {
            $tugas = $pembagian->get($id);
            $pola = collect($this->blok)->where('pembagian', $id)->pluck('panjang')->sortDesc()->implode(' + ');

            return PemeriksaJadwal::temuan(PemeriksaJadwal::PERINGATAN, "{$tugas->mataPelajaran->nama} kelas {$tugas->kelas->nama} ({$tugas->gtk->nama}) tidak muat sebagai blok yang direncanakan, sehingga dipasang {$pola} JP. Longgarkan permintaan wajib guru agar lebih rapi.");
        }, array_keys($this->dipecah));
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
