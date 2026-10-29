<?php

namespace App\Services\Arsip;

use App\Models\Ijazah;
use App\Models\Siswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Files scanned diplomas of one graduation year under their graduates. Files are paired with a target (see
 * {@see PencocokLulusan}) either by the person checking them, or — for {@see self::impor()} — by the NIS or NISN in
 * the file name: 0133438941.pdf, 0133438941-1.jpg, 0133438941_belakang.jpg.
 */
final class ImporArsipIjazah
{
    /**
     * Page suffixes stripped from a file name to find the identity number.
     */
    private const string POLA_HALAMAN = '/[-_ ]+(depan|belakang|hal(aman)?[-_ ]?\d+|\d{1,2})$/i';

    public function __construct(
        private SimpanBerkasArsip $penyimpan,
        private PencocokLulusan $pencocok,
    ) {}

    /**
     * Pair each file by the NIS/NISN in its name, then file them.
     *
     * @param  array<array-key, UploadedFile>  $daftarFile
     * @param  bool  $buatEntriBaru  Archive files of unknown alumni as new rows, to complete later.
     * @return array{diperbarui: int, dibuat: int, tidakCocok: list<string>}
     */
    public function impor(int $tahunLulus, array $daftarFile, bool $buatEntriBaru): array
    {
        usort($daftarFile, fn (UploadedFile $a, UploadedFile $b): int => strnatcasecmp($a->getClientOriginalName(), $b->getClientOriginalName()));

        $pasangan = [];
        $tidakCocok = [];

        foreach ($daftarFile as $file) {
            $nomor = self::nomorIdentitas($file->getClientOriginalName());
            $target = $nomor === null ? null : ($this->pencocok->tebakNomor($nomor, $tahunLulus) ?? ($buatEntriBaru ? "nomor:{$nomor}" : null));

            if ($target === null) {
                $tidakCocok[] = $file->getClientOriginalName();

                continue;
            }

            $pasangan[] = ['target' => $target, 'file' => $file];
        }

        return [...$this->simpanPasangan($tahunLulus, $pasangan), 'tidakCocok' => $tidakCocok];
    }

    /**
     * File every pair under its target, keeping the given order as the page order. Pairs with the same target
     * become pages of one diploma.
     *
     * @param  list<array{target: string, file: UploadedFile}>  $pasangan
     * @return array{diperbarui: int, dibuat: int}
     */
    public function simpanPasangan(int $tahunLulus, array $pasangan): array
    {
        $kelompok = [];

        foreach ($pasangan as $satuPasangan) {
            $kelompok[self::kunciTarget($satuPasangan['target'])][] = $satuPasangan;
        }

        $hasil = ['diperbarui' => 0, 'dibuat' => 0];

        foreach ($kelompok as $daftarPasangan) {
            $ijazah = $this->ijazahUntuk($tahunLulus, $daftarPasangan[0]['target']);

            if ($ijazah === null) {
                continue;
            }

            $path = array_map(fn (array $satuPasangan): string => $this->penyimpan->simpan($satuPasangan['file'], "ijazah/{$tahunLulus}"), $daftarPasangan);

            $hasil[$ijazah->wasRecentlyCreated ? 'dibuat' : 'diperbarui']++;

            DB::transaction(fn () => $ijazah->update(['berkas' => [...($ijazah->berkas ?? []), ...$path]]));
        }

        return $hasil;
    }

    /**
     * The NIS/NISN a file name stands for, or null when it holds no number.
     */
    public static function nomorIdentitas(string $namaFile): ?string
    {
        $nama = trim((string) preg_replace(self::POLA_HALAMAN, '', pathinfo($namaFile, PATHINFO_FILENAME)));

        return preg_match('/^\d{4,20}$/', $nama) === 1 ? $nama : null;
    }

    /**
     * New alumni typed in with the same name, in any letter case, are one graduate.
     */
    private static function kunciTarget(string $target): string
    {
        return str_starts_with($target, PencocokLulusan::BARU.':') ? mb_strtolower(trim($target)) : $target;
    }

    private function ijazahUntuk(int $tahunLulus, string $target): ?Ijazah
    {
        [$jenis, $nilai] = array_pad(explode(':', $target, 2), 2, '');
        $nilai = trim($nilai);

        return match ($jenis) {
            'ijazah' => Ijazah::query()->find($nilai),
            'siswa' => Siswa::withTrashed()->whereKey($nilai)->exists()
                ? Ijazah::query()->firstOrCreate(['tahun_lulus' => $tahunLulus, 'siswa_id' => (int) $nilai])
                : null,
            'nomor' => $this->ijazahUntukNomor($tahunLulus, $nilai),
            PencocokLulusan::BARU => $nilai === '' ? null : Ijazah::query()
                ->where('tahun_lulus', $tahunLulus)
                ->whereRaw('lower(nama) = ?', [mb_strtolower($nilai)])
                ->first() ?? Ijazah::query()->create(['tahun_lulus' => $tahunLulus, 'nama' => $nilai]),
            default => null,
        };
    }

    private function ijazahUntukNomor(int $tahunLulus, string $nomor): ?Ijazah
    {
        $target = $this->pencocok->tebakNomor($nomor, $tahunLulus);

        if ($target !== null) {
            return $this->ijazahUntuk($tahunLulus, $target);
        }

        // A ten-digit number is a NISN; anything else is taken as the madrasah's own NIS.
        return Ijazah::query()->create([
            'tahun_lulus' => $tahunLulus,
            ...(strlen($nomor) === 10 ? ['nisn' => $nomor] : ['nis' => $nomor]),
        ]);
    }
}
