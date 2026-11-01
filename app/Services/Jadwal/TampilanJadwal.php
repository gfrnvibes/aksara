<?php

namespace App\Services\Jadwal;

use App\Enums\Hari;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\PembagianMengajar;
use Illuminate\Support\Collection;

/**
 * A timetable laid out the way the madrasah prints it: days side by side with a column per class, each period
 * showing a short code, and a legend of the codes with each teacher's periods per class.
 *
 * Codes number the teachers by the Nomor Kode Jadwal in Data GTK (teachers without one are numbered after them, in
 * the order of the subjects they teach), with a letter for each subject when a teacher has more than one (01 A,
 * 01 B) in the order the subjects were assigned, and each code gets its own colour. So the codes stay the same
 * each time the timetable is rebuilt.
 */
class TampilanJadwal
{
    /**
     * Light colours that stay readable with black text, on screen and on paper.
     *
     * @var list<string>
     */
    public const array WARNA = [
        '#fca5a5', '#fde68a', '#86efac', '#93c5fd', '#c4b5fd', '#f9a8d4', '#5eead4', '#fdba74',
        '#a5b4fc', '#bef264', '#67e8f9', '#d8b4fe', '#fcd34d', '#6ee7b7', '#fda4af', '#bfdbfe',
        '#e9d5ff', '#fed7aa', '#d9f99d', '#99f6e4', '#fbcfe8', '#c7d2fe', '#fef08a', '#a7f3d0',
    ];

    /**
     * @var Collection<int, Kelas>
     */
    public Collection $kelas;

    /**
     * @var array<int, array{kode: string, warna: string}> Assignment => its code and colour.
     */
    public array $kode = [];

    /**
     * @var list<array{guru: string, baris: list<array{kode: string, warna: string, mapel: string, jp: array<int, int>, jumlah: int, terpasang: int}>}>
     */
    public array $legenda = [];

    /**
     * @var array<int, int> Class => periods assigned in total.
     */
    public array $totalPerKelas = [];

    /**
     * @var array<string, IsiJadwal> "class-day-period" => what is taught then.
     */
    protected array $sel = [];

    public function __construct(public Jadwal $jadwal, public PolaJam $pola)
    {
        $pembagian = PembagianMengajar::query()
            ->with(['gtk', 'kelas', 'mataPelajaran'])
            ->where('tahun_ajaran_id', $jadwal->tahun_ajaran_id)
            ->get();
        $isi = $jadwal->isi()->with(['pembagianMengajar.gtk', 'pembagianMengajar.mataPelajaran', 'kelas'])->get();

        foreach ($isi as $sel) {
            $this->sel["{$sel->kelas_id}-{$sel->hari->value}-{$sel->jp_ke}"] = $sel;
        }

        $this->kelas = $pembagian->pluck('kelas')
            ->merge($isi->pluck('kelas'))
            ->unique('id')
            ->sortBy([['tingkat', 'asc'], ['nama', 'asc']])
            ->values();

        $this->susunKode($pembagian, $isi->countBy('pembagian_mengajar_id')->all());
    }

    public static function untuk(Jadwal $jadwal): self
    {
        return new self($jadwal, PolaJam::muat());
    }

    /**
     * @param  Collection<int, PembagianMengajar>  $pembagian
     * @param  array<int, int>  $terpasang  Assignment => periods placed.
     */
    protected function susunKode(Collection $pembagian, array $terpasang): void
    {
        $perGuru = $pembagian
            ->groupBy('gtk_id')
            ->sortBy(fn (Collection $tugas): array => [
                $tugas->first()->gtk->nomor_kode ?? PHP_INT_MAX,
                $tugas->min(fn (PembagianMengajar $p): int => $p->mataPelajaran->urutan),
                $tugas->first()->gtk->nama,
            ])
            ->values();
        $nomorBerikut = (int) $perGuru->max(fn (Collection $tugas): ?int => $tugas->first()->gtk->nomor_kode) + 1;
        $warna = 0;

        foreach ($perGuru as $tugasGuru) {
            $nomor = $tugasGuru->first()->gtk->nomor_kode ?? $nomorBerikut++;
            $perMapel = $tugasGuru->groupBy('mata_pelajaran_id')
                ->sortBy(fn (Collection $tugas): int => $tugas->min('id'))
                ->values();
            $baris = [];

            foreach ($perMapel as $huruf => $tugasMapel) {
                $kode = str_pad((string) $nomor, 2, '0', STR_PAD_LEFT).($perMapel->count() > 1 ? ' '.chr(65 + $huruf) : '');
                $warnaKode = static::WARNA[$warna++ % count(static::WARNA)];
                $jp = [];
                $jumlahTerpasang = 0;

                foreach ($tugasMapel as $tugas) {
                    $this->kode[$tugas->id] = ['kode' => $kode, 'warna' => $warnaKode];
                    $jp[$tugas->kelas_id] = ($jp[$tugas->kelas_id] ?? 0) + $tugas->jp_per_minggu;
                    $this->totalPerKelas[$tugas->kelas_id] = ($this->totalPerKelas[$tugas->kelas_id] ?? 0) + $tugas->jp_per_minggu;
                    $jumlahTerpasang += $terpasang[$tugas->id] ?? 0;
                }

                $baris[] = [
                    'kode' => $kode,
                    'warna' => $warnaKode,
                    'mapel' => $tugasMapel->first()->mataPelajaran->nama,
                    'jp' => $jp,
                    'jumlah' => array_sum($jp),
                    'terpasang' => $jumlahTerpasang,
                ];
            }

            $this->legenda[] = ['guru' => $tugasGuru->first()->gtk->nama, 'baris' => $baris];
        }
    }

    /**
     * The school days in two groups of up to three, like the printed timetable.
     *
     * @return list<list<Hari>>
     */
    public function kelompokHari(): array
    {
        return array_chunk($this->pola->hari(), 3);
    }

    /**
     * How many rows the tallest day of the group has.
     *
     * @param  list<Hari>  $hari
     */
    public function jumlahBaris(array $hari): int
    {
        return max(array_map(fn (Hari $satuHari): int => $this->pola->baris($satuHari)->count(), $hari) ?: [0]);
    }

    public function isi(int $kelasId, Hari $hari, int $jpKe): ?IsiJadwal
    {
        return $this->sel["{$kelasId}-{$hari->value}-{$jpKe}"] ?? null;
    }

    /**
     * @return array{kode: string, warna: string}|null
     */
    public function kodeUntuk(?IsiJadwal $isi): ?array
    {
        return $isi === null ? null : ($this->kode[$isi->pembagian_mengajar_id] ?? ['kode' => '?', 'warna' => '#e5e7eb']);
    }

    public function totalJp(): int
    {
        return array_sum($this->totalPerKelas);
    }
}
