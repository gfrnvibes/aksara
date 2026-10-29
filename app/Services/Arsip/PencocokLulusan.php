<?php

namespace App\Services\Arsip;

use App\Enums\StatusSiswa;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\Ijazah;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Finds whose diploma a scanned file is. A target is written as "ijazah:{id}" (an archive row), "siswa:{id}" (a
 * student without an archive row yet), "nomor:{NIS/NISN}" or "baru:{name}" (an alumnus to add).
 */
final class PencocokLulusan
{
    public const string BARU = 'baru';

    /**
     * Words in file names that say nothing about whose diploma it is.
     *
     * @var list<string>
     */
    private const array KATA_UMUM = ['ijazah', 'skhun', 'skhu', 'scan', 'img', 'image', 'foto', 'dok', 'dokumen', 'hal', 'halaman', 'depan', 'belakang', 'mts', 'lulus', 'copy', 'salinan', 'pdf', 'jpg', 'jpeg', 'png'];

    /**
     * The graduate a file name points to: its NIS/NISN first, otherwise the one graduate of the year whose full
     * name appears in it. Null when nothing, or more than one graduate, fits.
     */
    public function tebak(string $namaFile, int $tahunLulus): ?string
    {
        $nomor = ImporArsipIjazah::nomorIdentitas($namaFile);

        return $nomor !== null ? $this->tebakNomor($nomor, $tahunLulus) : $this->tebakNama($namaFile, $tahunLulus);
    }

    public function tebakNomor(string $nomor, int $tahunLulus): ?string
    {
        $ijazahId = Ijazah::query()
            ->where('tahun_lulus', $tahunLulus)
            ->where(fn (Builder $query) => $query->where('nis', $nomor)->orWhere('nisn', $nomor))
            ->value('id');

        if ($ijazahId !== null) {
            return "ijazah:{$ijazahId}";
        }

        $siswaId = Siswa::withTrashed()
            ->where(fn (Builder $query) => $query->where('nis', $nomor)->orWhere('nisn', $nomor))
            ->value('id');

        return $siswaId === null ? null : "siswa:{$siswaId}";
    }

    public function tebakNama(string $namaFile, int $tahunLulus): ?string
    {
        $kataFile = self::kata(pathinfo($namaFile, PATHINFO_FILENAME));

        if ($kataFile === []) {
            return null;
        }

        $cocok = array_keys(array_filter($this->lulusanTahun($tahunLulus), function (string $nama) use ($kataFile): bool {
            $kataNama = static::kata($nama);

            return count($kataNama) >= 2 ? array_diff($kataNama, $kataFile) === [] : $kataNama === $kataFile;
        }));

        return count($cocok) === 1 ? $cocok[0] : null;
    }

    /**
     * Every choice for the graduate picker, grouped: the year's archive rows and graduates first, then every other
     * student.
     *
     * @return array<string, array<string, string>>
     */
    public function pilihan(int $tahunLulus): array
    {
        $lulusanTahun = $this->lulusanTahun($tahunLulus, denganNomor: true);
        $sudahDidaftar = array_map(
            fn (string $target): int => (int) substr($target, strlen('siswa:')),
            array_filter(array_keys($lulusanTahun), fn (string $target): bool => str_starts_with($target, 'siswa:')),
        );
        $siswaBerijazah = Ijazah::query()->where('tahun_lulus', $tahunLulus)->whereNotNull('siswa_id')->pluck('siswa_id')->all();

        $siswaLain = Siswa::query()
            ->whereNotIn('id', [...$sudahDidaftar, ...$siswaBerijazah])
            ->orderBy('nama_lengkap')
            ->get()
            ->mapWithKeys(fn (Siswa $siswa): array => ["siswa:{$siswa->id}" => self::labelSiswa($siswa)])
            ->all();

        return array_filter([
            "Lulusan {$tahunLulus}" => $lulusanTahun,
            'Siswa lainnya' => $siswaLain,
        ]);
    }

    /**
     * The graduates of a year: its archive rows, plus students who graduated that year or sat in the final grade in
     * the academic year ending then, and have no archive row yet.
     *
     * @return array<string, string> target => name (or label when $denganNomor)
     */
    private function lulusanTahun(int $tahunLulus, bool $denganNomor = false): array
    {
        $ijazah = Ijazah::query()->where('tahun_lulus', $tahunLulus)->whereNotNull('nama')->orderBy('nama')->get();
        $tingkatAkhir = max(array_keys(KelasResource::getTingkatOptions()));

        $siswa = Siswa::query()
            ->whereNotIn('id', $ijazah->pluck('siswa_id')->filter())
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('status', StatusSiswa::Lulus)->whereYear('tanggal_tamat', $tahunLulus))
                ->orWhereHas('riwayatKelas', fn (Builder $riwayat) => $riwayat
                    ->whereHas('kelas', fn (Builder $kelas) => $kelas->where('tingkat', $tingkatAkhir))
                    ->whereHas('tahunAjaran', fn (Builder $tahun) => $tahun->where('nama', ($tahunLulus - 1)."/{$tahunLulus}"))))
            ->orderBy('nama_lengkap')
            ->get();

        return [
            ...$ijazah->mapWithKeys(fn (Ijazah $baris): array => ["ijazah:{$baris->id}" => $denganNomor ? self::labelIjazah($baris) : (string) $baris->nama])->all(),
            ...$siswa->mapWithKeys(fn (Siswa $baris): array => ["siswa:{$baris->id}" => $denganNomor ? self::labelSiswa($baris) : $baris->nama_lengkap])->all(),
        ];
    }

    /**
     * The meaningful lower-case words of a name or file name.
     *
     * @return list<string>
     */
    public static function kata(string $teks): array
    {
        $kata = preg_split('/[^a-z]+/', Str::of($teks)->ascii()->lower()->toString(), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $kata,
            fn (string $satuKata): bool => strlen($satuKata) > 1 && ! in_array($satuKata, self::KATA_UMUM, true),
        )));
    }

    private static function labelIjazah(Ijazah $ijazah): string
    {
        return 'Arsip: '.($ijazah->nama ?? 'Nama belum diisi').(filled($ijazah->nis ?? $ijazah->nisn) ? ' ('.($ijazah->nis ?? $ijazah->nisn).')' : '');
    }

    private static function labelSiswa(Siswa $siswa): string
    {
        return "{$siswa->nis} - {$siswa->nama_lengkap}".($siswa->status !== StatusSiswa::Lulus ? " ({$siswa->status->getLabel()})" : '');
    }
}
