<?php

namespace App\Services\Jadwal;

use App\Enums\SifatPermintaanJadwal;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\PembagianMengajar;
use App\Models\PermintaanJadwal;

/**
 * Checks a timetable: teachers in two classes at once, periods outside the bell schedule, subjects given more or
 * fewer periods than assigned, and teachers' wishes it goes against.
 */
class PemeriksaJadwal
{
    public const string GALAT = 'galat';

    public const string PERINGATAN = 'peringatan';

    public const string INFO = 'info';

    public function __construct(protected PolaJam $pola) {}

    /**
     * @return list<array{tingkat: string, pesan: string}>
     */
    public function periksa(Jadwal $jadwal): array
    {
        $isi = $jadwal->isi()->with(['kelas', 'pembagianMengajar.gtk', 'pembagianMengajar.mataPelajaran', 'pembagianMengajar.kelas'])->get();
        $pembagian = PembagianMengajar::query()
            ->with(['gtk', 'mataPelajaran', 'kelas'])
            ->where('tahun_ajaran_id', $jadwal->tahun_ajaran_id)
            ->get();
        $permintaan = PermintaanJadwal::query()
            ->with(['gtk', 'mataPelajaran'])
            ->where('is_aktif', true)
            ->whereIn('gtk_id', $pembagian->pluck('gtk_id')->unique())
            ->get();

        return [
            ...$this->bentrokGuru($isi),
            ...$this->diLuarJam($isi),
            ...$this->jumlahJp($isi, $pembagian),
            ...$this->permintaanDilanggar($isi, $permintaan),
        ];
    }

    /**
     * @param  iterable<IsiJadwal>  $isi
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function bentrokGuru(iterable $isi): array
    {
        $perWaktu = [];

        foreach ($isi as $sel) {
            $perWaktu["{$sel->pembagianMengajar->gtk_id}-{$sel->hari->value}-{$sel->jp_ke}"][] = $sel;
        }

        $temuan = [];

        foreach ($perWaktu as $sel) {
            if (count($sel) < 2) {
                continue;
            }

            $kelas = collect($sel)->map(fn (IsiJadwal $isi): string => $isi->kelas->nama)->join(', ', ' dan ');
            $temuan[] = static::temuan(self::GALAT, "Bentrok: {$sel[0]->pembagianMengajar->gtk->nama} mengajar di kelas {$kelas} sekaligus pada {$sel[0]->hari->getLabel()} JP {$sel[0]->jp_ke}.");
        }

        return $temuan;
    }

    /**
     * @param  iterable<IsiJadwal>  $isi
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function diLuarJam(iterable $isi): array
    {
        $temuan = [];

        foreach ($isi as $sel) {
            if (! $this->pola->ada($sel->hari, $sel->jp_ke)) {
                $temuan[] = static::temuan(self::GALAT, "{$sel->pembagianMengajar->mataPelajaran->nama} kelas {$sel->kelas->nama} terpasang pada {$sel->hari->getLabel()} JP {$sel->jp_ke}, yang tidak ada di Jam Pelajaran.");
            }
        }

        return $temuan;
    }

    /**
     * @param  iterable<IsiJadwal>  $isi
     * @param  iterable<PembagianMengajar>  $pembagian
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function jumlahJp(iterable $isi, iterable $pembagian): array
    {
        $terpasang = [];

        foreach ($isi as $sel) {
            $terpasang[$sel->pembagian_mengajar_id] = ($terpasang[$sel->pembagian_mengajar_id] ?? 0) + 1;
        }

        $temuan = [];

        foreach ($pembagian as $tugas) {
            $jumlah = $terpasang[$tugas->id] ?? 0;

            if ($jumlah !== $tugas->jp_per_minggu) {
                $temuan[] = static::temuan(self::PERINGATAN, "{$tugas->mataPelajaran->nama} kelas {$tugas->kelas->nama} ({$tugas->gtk->nama}): terpasang {$jumlah} dari {$tugas->jp_per_minggu} JP.");
            }
        }

        return $temuan;
    }

    /**
     * @param  iterable<IsiJadwal>  $isi
     * @param  iterable<PermintaanJadwal>  $permintaan
     * @return list<array{tingkat: string, pesan: string}>
     */
    protected function permintaanDilanggar(iterable $isi, iterable $permintaan): array
    {
        $temuan = [];

        foreach ($permintaan as $keinginan) {
            $waktu = [];

            foreach ($isi as $sel) {
                $tugas = $sel->pembagianMengajar;

                if ($tugas->gtk_id === $keinginan->gtk_id && $keinginan->dilanggarOleh($sel->hari, $sel->jp_ke, $tugas->mata_pelajaran_id)) {
                    $waktu[] = "{$sel->hari->getLabel()} JP {$sel->jp_ke} ({$sel->kelas->nama})";
                }
            }

            if ($waktu === []) {
                continue;
            }

            $wajib = $keinginan->sifat === SifatPermintaanJadwal::Wajib;
            $temuan[] = static::temuan(
                $wajib ? self::GALAT : self::PERINGATAN,
                "{$keinginan->gtk->nama}: permintaan ".($wajib ? 'wajib ' : '')."\"{$keinginan->ringkasan()}\" tidak terpenuhi pada ".implode(', ', $waktu).'.',
            );
        }

        return $temuan;
    }

    /**
     * @return array{tingkat: string, pesan: string}
     */
    public static function temuan(string $tingkat, string $pesan): array
    {
        return ['tingkat' => $tingkat, 'pesan' => $pesan];
    }
}
