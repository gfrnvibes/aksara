<?php

namespace App\Services\Surat;

use App\Models\Siswa;
use App\Models\TahunAjaran;

/**
 * Fills a letter's placeholders with the madrasah's and a student's details. The AI only ever writes the
 * placeholders, so student data stays on the server.
 */
final class IsianSurat
{
    /**
     * Placeholders a letter may hold, grouped by where their value comes from, with what each stands for.
     *
     * @var array<string, array<string, string>>
     */
    public const array TANDA = [
        'Madrasah' => [
            '{nama_madrasah}' => 'Nama madrasah',
            '{alamat_madrasah}' => 'Alamat madrasah',
            '{tahun_ajaran}' => 'Tahun ajaran aktif, misalnya 2026/2027',
        ],
        'Siswa' => [
            '{nama_siswa}' => 'Nama lengkap siswa',
            '{nis}' => 'NIS',
            '{nisn}' => 'NISN',
            '{kelas}' => 'Kelas siswa sekarang',
            '{jenis_kelamin}' => 'Laki-laki atau Perempuan',
            '{tempat_tanggal_lahir}' => 'Tempat dan tanggal lahir, misalnya Garut, 5 Mei 2012',
        ],
        'Orang Tua' => [
            '{nama_orang_tua}' => 'Nama ayah; bila kosong, ibu; bila kosong, wali',
            '{nama_ayah}' => 'Nama ayah',
            '{nama_ibu}' => 'Nama ibu',
            '{nama_wali}' => 'Nama wali',
        ],
    ];

    /**
     * The sanitized HTML body with every known placeholder filled; placeholders about a student stay visible until
     * a student is chosen.
     */
    public static function isi(?string $html, ?Siswa $siswa): string
    {
        $aman = (string) str((string) $html)->sanitizeHtml();

        return strtr($aman, array_map(fn (string $nilai): string => e($nilai), self::nilaiTerisi($siswa)));
    }

    /**
     * Plain text, such as the subject or the addressee, with every known placeholder filled. It is not escaped:
     * the view escapes it when printing.
     */
    public static function teks(?string $teks, ?Siswa $siswa): string
    {
        return strtr((string) $teks, self::nilaiTerisi($siswa));
    }

    /**
     * @return array<string, string>
     */
    private static function nilaiTerisi(?Siswa $siswa): array
    {
        return array_filter(self::nilai($siswa), fn (?string $nilai): bool => filled($nilai));
    }

    /**
     * @return array<string, string|null>
     */
    private static function nilai(?Siswa $siswa): array
    {
        $siswa?->loadMissing(['kelas', 'ayah', 'ibu', 'wali']);

        $tempatTanggalLahir = $siswa === null ? null : collect([
            $siswa->tempat_lahir,
            $siswa->tanggal_lahir?->translatedFormat('d F Y'),
        ])->filter()->implode(', ');

        return [
            '{nama_madrasah}' => (string) config('sekolah.nama'),
            '{alamat_madrasah}' => config('sekolah.alamat'),
            '{tahun_ajaran}' => TahunAjaran::query()->where('is_aktif', true)->value('nama'),
            '{nama_siswa}' => $siswa?->nama_lengkap,
            '{nis}' => $siswa?->nis,
            '{nisn}' => $siswa?->nisn,
            '{kelas}' => $siswa?->kelas?->nama,
            '{jenis_kelamin}' => $siswa?->jenis_kelamin->getLabel(),
            '{tempat_tanggal_lahir}' => $tempatTanggalLahir,
            '{nama_orang_tua}' => $siswa === null ? null : ($siswa->ayah->nama ?? $siswa->ibu->nama ?? $siswa->wali?->nama),
            '{nama_ayah}' => $siswa?->ayah?->nama,
            '{nama_ibu}' => $siswa?->ibu?->nama,
            '{nama_wali}' => $siswa?->wali?->nama,
        ];
    }
}
