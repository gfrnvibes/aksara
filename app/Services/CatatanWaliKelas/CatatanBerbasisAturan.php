<?php

namespace App\Services\CatatanWaliKelas;

use App\Enums\KeputusanKenaikan;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

/**
 * Writes the homeroom note from fixed rules: no network, no data leaves the server, same input gives the same text.
 */
class CatatanBerbasisAturan implements PembuatCatatan
{
    /**
     * Gap between average skill and knowledge scores that is worth mentioning.
     */
    public const int SELISIH_TEORI_PRAKTIK = 5;

    /**
     * Sick plus permitted days in one semester from which the note encourages keeping healthy.
     */
    public const int BATAS_SAKIT_IZIN = 6;

    /**
     * @var list<string>
     */
    protected const array PENUTUP = [
        'Tetap semangat belajar dan tingkatkan prestasi pada semester berikutnya.',
        'Terus pertahankan semangat belajar dan raih hasil yang lebih baik pada semester berikutnya.',
        'Semoga semester berikutnya ananda semakin rajin dan berprestasi.',
    ];

    public function buat(RingkasanRapor $ringkasan): string
    {
        $kalimat = array_filter([
            $this->pembuka($ringkasan),
            $this->kekuatan($ringkasan),
            $this->perluPerhatian($ringkasan),
            $this->teoriDanPraktik($ringkasan),
            $this->sikap($ringkasan),
            $this->ekstrakurikuler($ringkasan),
            $this->akhlakDanKepribadian($ringkasan),
            $this->kehadiran($ringkasan),
            $this->keputusan($ringkasan) ?? static::PENUTUP[crc32(serialize($ringkasan)) % count(static::PENUTUP)],
        ]);

        return implode(' ', $kalimat);
    }

    protected function pembuka(RingkasanRapor $ringkasan): string
    {
        $rata = $ringkasan->rataRata();

        if ($rata === null) {
            return 'Ananda belum memiliki nilai mata pelajaran yang tercatat pada semester ini.';
        }

        $rataKkm = array_sum(array_column($ringkasan->mataPelajaranBernilai(), 'kkm')) / count($ringkasan->mataPelajaranBernilai());
        $teksRata = Number::format($rata, maxPrecision: 1, locale: 'id');

        return match (true) {
            $rata >= 90 => "Ananda meraih hasil belajar yang sangat baik pada semester ini dengan rata-rata nilai {$teksRata}.",
            $rata >= 80 => "Ananda menunjukkan hasil belajar yang baik dengan rata-rata nilai {$teksRata}.",
            $rata >= $rataKkm => "Ananda telah mencapai ketuntasan belajar dengan rata-rata nilai {$teksRata}.",
            default => "Ananda masih perlu meningkatkan hasil belajar karena rata-rata nilai semester ini {$teksRata}.",
        };
    }

    protected function kekuatan(RingkasanRapor $ringkasan): ?string
    {
        $unggul = collect($ringkasan->mataPelajaranBernilai())
            ->filter(fn (array $mapel): bool => $mapel['rata'] >= max($mapel['kkm'], 80))
            ->sortByDesc('rata')
            ->take(2)
            ->pluck('nama')
            ->all();

        return $unggul === [] ? null : 'Kemampuan terbaik terlihat pada '.Arr::join($unggul, ', ', ' dan ').'.';
    }

    protected function perluPerhatian(RingkasanRapor $ringkasan): ?string
    {
        $bernilai = $ringkasan->mataPelajaranBernilai();

        if ($bernilai === []) {
            return null;
        }

        $diBawahKkm = collect($bernilai)
            ->filter(fn (array $mapel): bool => $mapel['rata'] < $mapel['kkm'])
            ->pluck('nama')
            ->all();

        if ($diBawahKkm === []) {
            return 'Seluruh mata pelajaran telah mencapai KKM.';
        }

        return 'Nilai '.Arr::join($diBawahKkm, ', ', ' dan ').' masih di bawah KKM sehingga perlu bimbingan dan latihan tambahan.';
    }

    protected function teoriDanPraktik(RingkasanRapor $ringkasan): ?string
    {
        $pengetahuan = $ringkasan->rataRataJenis('pengetahuan');
        $keterampilan = $ringkasan->rataRataJenis('keterampilan');

        if ($pengetahuan === null || $keterampilan === null) {
            return null;
        }

        return match (true) {
            $keterampilan - $pengetahuan >= static::SELISIH_TEORI_PRAKTIK => 'Kemampuan praktik ananda lebih menonjol daripada penguasaan teori, sehingga pemahaman konsep perlu diperkuat.',
            $pengetahuan - $keterampilan >= static::SELISIH_TEORI_PRAKTIK => 'Penguasaan teori ananda sudah baik; perbanyak latihan praktik agar keterampilan ikut berkembang.',
            default => null,
        };
    }

    protected function sikap(RingkasanRapor $ringkasan): ?string
    {
        $dinilai = array_filter($ringkasan->mataPelajaran, fn (array $mapel): bool => $mapel['sikap'] !== null);

        if ($dinilai === []) {
            return null;
        }

        $perluDibina = array_column(array_filter($dinilai, fn (array $mapel): bool => in_array($mapel['sikap'], ['C', 'K'], true)), 'nama');

        if ($perluDibina !== []) {
            return 'Sikap dalam mengikuti pelajaran '.Arr::join($perluDibina, ', ', ' dan ').' masih perlu dibina.';
        }

        $sangatBaik = count(array_filter($dinilai, fn (array $mapel): bool => $mapel['sikap'] === 'SB'));

        return $sangatBaik * 2 >= count($dinilai)
            ? 'Sikap ananda dalam mengikuti pelajaran sangat baik.'
            : 'Sikap ananda dalam mengikuti pelajaran baik.';
    }

    protected function ekstrakurikuler(RingkasanRapor $ringkasan): ?string
    {
        if ($ringkasan->ekstrakurikuler === []) {
            return null;
        }

        $kalimat = [];
        $berprestasi = array_column(array_filter($ringkasan->ekstrakurikuler, fn (array $ekskul): bool => $ekskul['nilai'] === 'A'), 'nama');
        $kurang = array_column(array_filter($ringkasan->ekstrakurikuler, fn (array $ekskul): bool => in_array($ekskul['nilai'], ['C', 'D'], true)), 'nama');
        $lainnya = array_diff(array_column($ringkasan->ekstrakurikuler, 'nama'), $berprestasi, $kurang);

        if ($berprestasi !== []) {
            $kalimat[] = 'Ananda aktif dan berprestasi dalam kegiatan '.Arr::join($berprestasi, ', ', ' dan ').'.';
        }

        if ($lainnya !== []) {
            $kalimat[] = ($berprestasi === [] ? 'Ananda' : 'Ananda juga').' mengikuti kegiatan '.Arr::join(array_values($lainnya), ', ', ' dan ').' dengan baik.';
        }

        if ($kurang !== []) {
            $kalimat[] = 'Keaktifan dalam kegiatan '.Arr::join($kurang, ', ', ' dan ').' masih perlu ditingkatkan.';
        }

        return implode(' ', $kalimat);
    }

    protected function akhlakDanKepribadian(RingkasanRapor $ringkasan): ?string
    {
        $predikat = array_filter([$ringkasan->akhlak, $ringkasan->kepribadian]);

        if ($predikat === []) {
            return null;
        }

        return match (true) {
            array_intersect($predikat, ['C', 'D']) !== [] => 'Akhlak dan kepribadian ananda perlu terus dibina bersama oleh madrasah dan orang tua.',
            array_diff($predikat, ['A']) === [] => 'Akhlak dan kepribadian ananda sangat baik; pertahankan sikap santun di madrasah maupun di rumah.',
            default => 'Akhlak dan kepribadian ananda baik; terus biasakan sikap santun dan disiplin.',
        };
    }

    /**
     * The end-of-year decision closes an even-semester note in place of the usual encouragement.
     */
    protected function keputusan(RingkasanRapor $ringkasan): ?string
    {
        return match ($ringkasan->keputusan) {
            KeputusanKenaikan::Naik => $ringkasan->kelasTujuan() !== null
                ? "Selamat, ananda dinyatakan naik ke kelas {$ringkasan->kelasTujuan()}; tetap semangat belajar di kelas yang baru."
                : 'Selamat, ananda dinyatakan naik kelas; tetap semangat belajar di kelas yang baru.',
            KeputusanKenaikan::Lulus => 'Selamat atas kelulusan ananda dari madrasah; semoga sukses di jenjang pendidikan berikutnya.',
            KeputusanKenaikan::TinggalDiKelas => 'Ananda belum dapat naik kelas pada tahun ini. Jadikan ini kesempatan untuk memperkuat pemahaman, dengan dukungan madrasah dan orang tua.',
            null => null,
        };
    }

    protected function kehadiran(RingkasanRapor $ringkasan): string
    {
        $batas = (int) config('sekolah.batas_tanpa_keterangan', 2);

        return match (true) {
            $ringkasan->tanpaKeterangan > $batas => "Kehadiran perlu ditingkatkan karena terdapat {$ringkasan->tanpaKeterangan} hari tanpa keterangan.",
            $ringkasan->tanpaKeterangan > 0 => "Masih terdapat {$ringkasan->tanpaKeterangan} hari tanpa keterangan; usahakan selalu hadir.",
            $ringkasan->sakit + $ringkasan->izin >= static::BATAS_SAKIT_IZIN => 'Jaga kesehatan agar dapat mengikuti pembelajaran secara penuh.',
            $ringkasan->sakit + $ringkasan->izin === 0 => 'Kehadiran ananda sangat baik.',
            default => 'Kehadiran ananda sudah baik.',
        };
    }
}
