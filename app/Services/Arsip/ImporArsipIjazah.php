<?php

namespace App\Services\Arsip;

use App\Models\Ijazah;
use App\Models\Siswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Files a batch of scanned diplomas of one graduation year, matching each file to a graduate by the NIS or NISN in
 * its name. Pages of one diploma share the number with a suffix: 0133438941.pdf, 0133438941-1.jpg,
 * 0133438941_belakang.jpg.
 */
final class ImporArsipIjazah
{
    /**
     * Page suffixes stripped from a file name to find the identity number.
     */
    private const string POLA_HALAMAN = '/[-_ ]+(depan|belakang|hal(aman)?[-_ ]?\d+|\d{1,2})$/i';

    public function __construct(private SimpanBerkasArsip $penyimpan) {}

    /**
     * @param  array<array-key, UploadedFile>  $daftarFile
     * @param  bool  $buatEntriBaru  Archive files of unknown alumni as new rows, to complete later.
     * @return array{diperbarui: int, dibuat: int, tidakCocok: list<string>}
     */
    public function impor(int $tahunLulus, array $daftarFile, bool $buatEntriBaru): array
    {
        $kelompok = [];
        $tidakCocok = [];

        foreach ($daftarFile as $file) {
            $nomor = self::nomorIdentitas($file->getClientOriginalName());

            if ($nomor === null) {
                $tidakCocok[] = $file->getClientOriginalName();

                continue;
            }

            $kelompok[$nomor][] = $file;
        }

        ksort($kelompok);
        $hasil = ['diperbarui' => 0, 'dibuat' => 0, 'tidakCocok' => $tidakCocok];

        foreach ($kelompok as $nomor => $daftarBerkas) {
            $nomor = (string) $nomor;
            $ijazah = $this->cariAtauBuat($tahunLulus, $nomor, $buatEntriBaru);

            if ($ijazah === null) {
                array_push($hasil['tidakCocok'], ...array_map(fn (UploadedFile $file): string => $file->getClientOriginalName(), $daftarBerkas));

                continue;
            }

            usort($daftarBerkas, fn (UploadedFile $a, UploadedFile $b): int => strnatcasecmp($a->getClientOriginalName(), $b->getClientOriginalName()));

            $path = array_map(fn (UploadedFile $file): string => $this->penyimpan->simpan($file, "ijazah/{$tahunLulus}"), $daftarBerkas);

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

    private function cariAtauBuat(int $tahunLulus, string $nomor, bool $buatEntriBaru): ?Ijazah
    {
        $ijazah = Ijazah::query()
            ->where('tahun_lulus', $tahunLulus)
            ->where(fn ($query) => $query->where('nis', $nomor)->orWhere('nisn', $nomor))
            ->first();

        if ($ijazah !== null) {
            return $ijazah;
        }

        $siswaId = Siswa::withTrashed()
            ->where(fn ($query) => $query->where('nis', $nomor)->orWhere('nisn', $nomor))
            ->value('id');

        if ($siswaId !== null) {
            return Ijazah::query()->create(['tahun_lulus' => $tahunLulus, 'siswa_id' => $siswaId]);
        }

        if (! $buatEntriBaru) {
            return null;
        }

        // A ten-digit number is a NISN; anything else is taken as the madrasah's own NIS.
        return Ijazah::query()->create([
            'tahun_lulus' => $tahunLulus,
            ...(strlen($nomor) === 10 ? ['nisn' => $nomor] : ['nis' => $nomor]),
        ]);
    }
}
