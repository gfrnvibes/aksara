<?php

namespace App\Services\Cbt;

use App\Enums\JenisSoal;
use App\Models\ButirUjian;
use App\Models\Soal;
use App\Models\Ujian;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The printed form of an exam packet: questions grouped by kind in a fixed order and numbered straight through,
 * each with its lettered choices and its answer key.
 *
 * Packet A keeps the order the teacher set; every other packet shuffles the questions within each kind and the
 * choices of PG questions. The shuffle is seeded by exam, packet and question, so a reprint matches the first
 * print and its key. The right column of a matching question is always shuffled, or its key would read 1-A, 2-B…
 */
class NaskahUjian
{
    /**
     * @var list<JenisSoal>
     */
    public const array URUTAN_JENIS = [JenisSoal::PilihanGanda, JenisSoal::PgKompleks, JenisSoal::BenarSalah, JenisSoal::Menjodohkan, JenisSoal::Esai];

    /**
     * @return list<array{jenis: JenisSoal, butir: list<array{nomor: int, soal: Soal, bobot: int, opsi: list<array{huruf: string, teks: string|null, gambar: string|null, benar: bool, asal: int}>, kanan: list<array{huruf: string, teks: string}>, kunci: string, kunciHuruf: string}>}>
     */
    public static function susun(Ujian $ujian, string $paket): array
    {
        $butir = $ujian->butir()->with('soal')->get();
        $bagian = [];
        $nomor = 0;

        foreach (static::URUTAN_JENIS as $jenis) {
            $sejenis = $butir->filter(fn (ButirUjian $b): bool => $b->soal->jenis === $jenis)->values()->all();

            if ($sejenis === []) {
                continue;
            }

            if ($paket !== 'A') {
                $sejenis = static::acak($sejenis, "{$ujian->id}|{$paket}|{$jenis->value}");
            }

            $daftar = [];

            foreach ($sejenis as $b) {
                $daftar[] = static::butir($b, ++$nomor, $paket === 'A' ? null : "{$ujian->id}|{$paket}|{$b->soal_id}");
            }

            $bagian[] = ['jenis' => $jenis, 'butir' => $daftar];
        }

        return $bagian;
    }

    /**
     * @return array{nomor: int, soal: Soal, bobot: int, opsi: list<array{huruf: string, teks: string|null, gambar: string|null, benar: bool, asal: int}>, kanan: list<array{huruf: string, teks: string}>, kunci: string, kunciHuruf: string}
     */
    protected static function butir(ButirUjian $butir, int $nomor, ?string $benihAcak): array
    {
        $soal = $butir->soal;
        $baris = array_values($soal->opsi ?? []);
        $opsi = [];
        $kanan = [];

        // Remember each row's place in the bank, so answers on any packet can be traced back to the same choice.
        $baris = array_map(fn (array $b, int $i): array => [...$b, 'asal' => $i], $baris, array_keys($baris));

        if (in_array($soal->jenis, [JenisSoal::PilihanGanda, JenisSoal::PgKompleks], true) && $benihAcak !== null) {
            $baris = static::acak($baris, $benihAcak);
        }

        foreach ($baris as $i => $b) {
            $opsi[] = [
                'huruf' => $soal->jenis === JenisSoal::BenarSalah || $soal->jenis === JenisSoal::Menjodohkan ? (string) ($i + 1) : chr(65 + $i),
                'teks' => $b['teks'] ?? null,
                'gambar' => $b['gambar'] ?? null,
                'benar' => (bool) ($b['benar'] ?? false),
                'asal' => $b['asal'],
            ];
        }

        if ($soal->jenis === JenisSoal::Menjodohkan) {
            $pasangan = array_values(array_unique(array_map(fn (array $b): string => (string) ($b['pasangan'] ?? ''), $baris)));

            foreach (static::acak($pasangan, "kanan|{$soal->id}|".($benihAcak ?? 'A')) as $i => $teks) {
                $kanan[] = ['huruf' => chr(65 + $i), 'teks' => $teks];
            }
        }

        return [
            'nomor' => $nomor,
            'soal' => $soal,
            'bobot' => $butir->bobot,
            'opsi' => $opsi,
            'kanan' => $kanan,
            'kunci' => static::kunci($soal, $baris, $opsi, $kanan),
            'kunciHuruf' => static::kunciHuruf($soal, $baris, $opsi, $kanan),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @param  list<array{huruf: string, teks: string|null, gambar: string|null, benar: bool, asal: int}>  $opsi
     * @param  list<array{huruf: string, teks: string}>  $kanan
     */
    protected static function kunci(Soal $soal, array $baris, array $opsi, array $kanan): string
    {
        $hurufKanan = array_column($kanan, 'huruf', 'teks');

        return match ($soal->jenis) {
            JenisSoal::PilihanGanda, JenisSoal::PgKompleks => implode(', ', array_column(array_filter($opsi, fn (array $o): bool => $o['benar']), 'huruf')),
            JenisSoal::BenarSalah => implode(', ', array_map(fn (array $o): string => $o['huruf'].'. '.($o['benar'] ? 'B' : 'S'), $opsi)),
            JenisSoal::Menjodohkan => implode(', ', array_map(fn (array $o, array $b): string => $o['huruf'].'-'.($hurufKanan[(string) ($b['pasangan'] ?? '')] ?? '?'), $opsi, $baris)),
            JenisSoal::Esai => (string) $soal->pembahasan,
        };
    }

    /**
     * The key as the letters a student marks, in the form answers are stored: "B" (PG), "AC" (PG kompleks),
     * "BSB" (benar/salah, one per statement), "CAB" (menjodohkan, one per row), "" (esai).
     *
     * @param  list<array<string, mixed>>  $baris
     * @param  list<array{huruf: string, teks: string|null, gambar: string|null, benar: bool, asal: int}>  $opsi
     * @param  list<array{huruf: string, teks: string}>  $kanan
     */
    protected static function kunciHuruf(Soal $soal, array $baris, array $opsi, array $kanan): string
    {
        $hurufKanan = array_column($kanan, 'huruf', 'teks');

        return match ($soal->jenis) {
            JenisSoal::PilihanGanda, JenisSoal::PgKompleks => implode('', array_column(array_filter($opsi, fn (array $o): bool => $o['benar']), 'huruf')),
            JenisSoal::BenarSalah => implode('', array_map(fn (array $o): string => $o['benar'] ? 'B' : 'S', $opsi)),
            JenisSoal::Menjodohkan => implode('', array_map(fn (array $b): string => $hurufKanan[(string) ($b['pasangan'] ?? '')] ?? '?', $baris)),
            JenisSoal::Esai => '',
        };
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $daftar
     * @return list<T>
     */
    protected static function acak(array $daftar, string $benih): array
    {
        return (new Randomizer(new Mt19937(crc32($benih))))->shuffleArray($daftar);
    }
}
