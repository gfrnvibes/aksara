<?php

namespace App\Services\Jadwal;

use App\Enums\Hari;
use App\Enums\JenisJamPelajaran;
use App\Models\JamPelajaran;
use Illuminate\Support\Collection;

/**
 * The weekly bell schedule as the timetable sees it: which lesson periods each day has, and which of them run
 * on without a break or prayer in between (a session), so a double lesson can stay in one session.
 */
class PolaJam
{
    /**
     * @var array<int, Collection<int, JamPelajaran>> Day => the day's rows in time order.
     */
    protected array $baris = [];

    /**
     * @var array<int, array<int, int>> Day => lesson period => session number.
     */
    protected array $sesi = [];

    /**
     * @param  Collection<int, JamPelajaran>  $jam
     */
    public function __construct(Collection $jam)
    {
        foreach (Hari::cases() as $hari) {
            $barisHari = $jam->filter(fn (JamPelajaran $baris): bool => $baris->hari === $hari)->sortBy('mulai')->values();

            if ($barisHari->doesntContain(fn (JamPelajaran $baris): bool => $baris->jenis === JenisJamPelajaran::Pelajaran)) {
                continue;
            }

            $this->baris[$hari->value] = $barisHari;
            $sesi = 0;
            $sebelumnyaPelajaran = false;

            foreach ($barisHari as $baris) {
                $pelajaran = $baris->jenis === JenisJamPelajaran::Pelajaran;

                if ($pelajaran && ! $sebelumnyaPelajaran) {
                    $sesi++;
                }

                if ($pelajaran) {
                    $this->sesi[$hari->value][(int) $baris->jp_ke] = $sesi;
                }

                $sebelumnyaPelajaran = $pelajaran;
            }
        }
    }

    public static function muat(): self
    {
        return new self(JamPelajaran::query()->get());
    }

    /**
     * @return list<Hari>
     */
    public function hari(): array
    {
        return array_map(Hari::from(...), array_keys($this->baris));
    }

    /**
     * The day's lesson periods, in order.
     *
     * @return list<int>
     */
    public function jp(Hari $hari): array
    {
        return array_keys($this->sesi[$hari->value] ?? []);
    }

    public function ada(Hari $hari, int $jpKe): bool
    {
        return isset($this->sesi[$hari->value][$jpKe]);
    }

    /**
     * Whether two lesson periods of the day run on without a break between them.
     */
    public function satuSesi(Hari $hari, int $jpA, int $jpB): bool
    {
        return ($this->sesi[$hari->value][$jpA] ?? null) === ($this->sesi[$hari->value][$jpB] ?? -1);
    }

    /**
     * @return Collection<int, JamPelajaran>
     */
    public function baris(Hari $hari): Collection
    {
        return $this->baris[$hari->value] ?? collect();
    }

    public function jamUntuk(Hari $hari, int $jpKe): ?JamPelajaran
    {
        return $this->baris($hari)->first(fn (JamPelajaran $baris): bool => $baris->jp_ke === $jpKe);
    }

    public function jumlahJp(): int
    {
        return array_sum(array_map(count(...), $this->sesi));
    }

    public function kosong(): bool
    {
        return $this->sesi === [];
    }
}
