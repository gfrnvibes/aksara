<?php

namespace App\Services\Cbt;

use App\Enums\JenisSoal;
use App\Models\Kelas;
use App\Models\PesertaUjian;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;

/**
 * Who sits an exam, how their answers score, and how each question performed.
 *
 * Scoring is proportional: PG kompleks earns (right picks − wrong picks) ÷ keyed choices, benar/salah and
 * menjodohkan earn the share of rows answered right, and the essay score is entered by the teacher.
 */
class PenilaianUjian
{
    /** Share of students counted as the upper and the lower group for the discrimination index. */
    public const float KELOMPOK = 0.27;

    /** A wrong choice works as a distractor once at least this share of students picks it. */
    public const float PENGECOH_MIN = 0.05;

    /**
     * Adds the students of the exam's grade that are not listed yet, handing out packets in turn by roll order
     * within each class. Existing participants keep their packet and answers.
     */
    public static function muatPeserta(Ujian $ujian): int
    {
        $sudah = $ujian->peserta()->pluck('siswa_id')->flip();
        $paket = $ujian->daftarPaket();
        $baru = [];

        $kelas = Kelas::query()->where('tingkat', $ujian->tingkat)->where('is_aktif', true)->orderBy('nama')->pluck('id');

        foreach ($kelas as $kelasId) {
            $siswa = Siswa::query()->anggotaKelas($kelasId, $ujian->tahun_ajaran_id)->orderBy('nama_lengkap')->pluck('id');

            foreach ($siswa as $urutan => $siswaId) {
                if (! $sudah->has($siswaId)) {
                    $baru[] = ['siswa_id' => $siswaId, 'kelas_id' => $kelasId, 'paket' => $paket[$urutan % count($paket)]];
                }
            }
        }

        $ujian->peserta()->createMany($baru);

        return count($baru);
    }

    /**
     * Strips what the teacher typed down to the letters that count: "a, c" → "AC".
     */
    public static function rapikan(mixed $jawaban): string
    {
        return (string) preg_replace('/[^A-Z]/', '', strtoupper((string) $jawaban));
    }

    /**
     * The score of one answer, from 0 to the question's weight.
     *
     * @param  array{soal: Soal, bobot: int, kunciHuruf: string}  $butir  A question as laid out in the student's packet.
     */
    public static function skor(array $butir, mixed $jawaban): float
    {
        $bobot = $butir['bobot'];
        $kunci = $butir['kunciHuruf'];

        if ($butir['soal']->jenis === JenisSoal::Esai) {
            return is_numeric($jawaban) ? max(0.0, min((float) $bobot, (float) $jawaban)) : 0.0;
        }

        $jawaban = static::rapikan($jawaban);

        $bagian = match ($butir['soal']->jenis) {
            JenisSoal::PilihanGanda => $jawaban === $kunci ? 1.0 : 0.0,
            JenisSoal::PgKompleks => max(0, count(array_intersect(array_unique(str_split($jawaban)), str_split($kunci)))
                - count(array_diff(array_unique(str_split($jawaban)), str_split($kunci)))) / max(1, strlen($kunci)),
            default => count(array_filter(str_split($kunci), fn (string $k, int $i): bool => ($jawaban[$i] ?? '') === $k, ARRAY_FILTER_USE_BOTH)) / max(1, strlen($kunci)),
        };

        return round($bobot * $bagian, 2);
    }

    /**
     * Scores every participant again: after answers are entered, or after the exam's questions or weights change.
     */
    public static function nilaiUlang(Ujian $ujian, ?PesertaUjian $hanya = null): void
    {
        $naskah = [];
        $total = (int) $ujian->butir()->sum('bobot');

        foreach ($hanya !== null ? [$hanya] : $ujian->peserta()->get() as $peserta) {
            if ($peserta->jawaban === null) {
                $peserta->update(['skor' => null, 'nilai' => null]);

                continue;
            }

            $naskah[$peserta->paket] ??= array_merge(...array_column(NaskahUjian::susun($ujian, $peserta->paket), 'butir'));
            $skor = [];

            foreach ($naskah[$peserta->paket] as $butir) {
                $skor[$butir['soal']->id] = static::skor($butir, $peserta->jawaban[$butir['soal']->id] ?? null);
            }

            $peserta->update(['skor' => $skor, 'nilai' => $total > 0 ? round(array_sum($skor) / $total * 100, 2) : 0]);
        }
    }

    /**
     * Item analysis over the students who have answers, one row per question in packet A order.
     *
     * @return array{peserta: int, rerata: float|null, tertinggi: float|null, terendah: float|null, butir: list<array{nomor: int, soal: Soal, bobot: int, kesukaran: float|null, dayaBeda: float|null, pengecoh: list<array{huruf: string, persen: float, benar: bool, berfungsi: bool}>, rekomendasi: string}>}
     */
    public static function analisis(Ujian $ujian): array
    {
        $peserta = $ujian->peserta()->whereNotNull('nilai')->orderByDesc('nilai')->get();
        $n = $peserta->count();
        $kelompok = $n >= 2 ? max(1, (int) round($n * static::KELOMPOK)) : 0;
        $atas = $peserta->take($kelompok);
        $bawah = $peserta->reverse()->take($kelompok);
        $naskah = [];
        $hasil = [];

        foreach (array_merge(...array_column(NaskahUjian::susun($ujian, 'A'), 'butir')) as $butir) {
            $id = $butir['soal']->id;
            $proporsi = fn (PesertaUjian $p): float => ($p->skor[$id] ?? 0) / max(1, $butir['bobot']);
            $kesukaran = $n > 0 ? round($peserta->avg($proporsi), 2) : null;
            $dayaBeda = $kelompok > 0 ? round($atas->avg($proporsi) - $bawah->avg($proporsi), 2) : null;
            $pengecoh = [];

            if ($butir['soal']->jenis === JenisSoal::PilihanGanda && $n > 0) {
                $dipilih = array_fill(0, count($butir['opsi']), 0);

                foreach ($peserta as $p) {
                    // Packets order the choices differently; trace the letter back to the choice in the bank.
                    $naskah[$p->paket] ??= collect(array_merge(...array_column(NaskahUjian::susun($ujian, $p->paket), 'butir')))->keyBy(fn (array $b): int => $b['soal']->id);
                    $huruf = static::rapikan($p->jawaban[$id] ?? '');
                    $opsi = collect($naskah[$p->paket][$id]['opsi'] ?? [])->firstWhere('huruf', $huruf);

                    if ($opsi !== null) {
                        $dipilih[$opsi['asal']]++;
                    }
                }

                foreach ($butir['opsi'] as $o) {
                    $persen = round($dipilih[$o['asal']] / $n, 2);
                    $pengecoh[] = ['huruf' => $o['huruf'], 'persen' => $persen, 'benar' => $o['benar'], 'berfungsi' => $o['benar'] || $persen >= static::PENGECOH_MIN];
                }
            }

            $hasil[] = [
                'nomor' => $butir['nomor'],
                'soal' => $butir['soal'],
                'bobot' => $butir['bobot'],
                'kesukaran' => $kesukaran,
                'dayaBeda' => $dayaBeda,
                'pengecoh' => $pengecoh,
                'rekomendasi' => static::rekomendasi($kesukaran, $dayaBeda, $pengecoh),
            ];
        }

        return [
            'peserta' => $n,
            'rerata' => $n > 0 ? round((float) $peserta->avg('nilai'), 2) : null,
            'tertinggi' => $n > 0 ? (float) $peserta->max('nilai') : null,
            'terendah' => $n > 0 ? (float) $peserta->min('nilai') : null,
            'butir' => $hasil,
        ];
    }

    public static function labelKesukaran(?float $p): string
    {
        return match (true) {
            $p === null => '-',
            $p < 0.3 => 'Sukar',
            $p <= 0.7 => 'Sedang',
            default => 'Mudah',
        };
    }

    public static function labelDayaBeda(?float $d): string
    {
        return match (true) {
            $d === null => '-',
            $d >= 0.4 => 'Sangat baik',
            $d >= 0.3 => 'Baik',
            $d >= 0.2 => 'Cukup',
            default => 'Jelek',
        };
    }

    /**
     * @param  list<array{berfungsi: bool}>  $pengecoh
     */
    protected static function rekomendasi(?float $kesukaran, ?float $dayaBeda, array $pengecoh): string
    {
        return match (true) {
            $kesukaran === null || $dayaBeda === null => '-',
            $dayaBeda < 0.2 => 'Ganti',
            $dayaBeda < 0.3 || $kesukaran < 0.3 || $kesukaran > 0.7 || in_array(false, array_column($pengecoh, 'berfungsi'), true) => 'Revisi',
            default => 'Pakai lagi',
        };
    }
}
