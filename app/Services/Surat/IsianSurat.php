<?php

namespace App\Services\Surat;

use App\Models\Siswa;
use App\Models\TahunAjaran;

/**
 * Fills a letter body's placeholders with the madrasah's and a student's details. The AI only ever writes the
 * placeholders, so student data stays on the server.
 */
final class IsianSurat
{
    /**
     * Placeholders a letter body may hold, with what each stands for.
     *
     * @var array<string, string>
     */
    public const array TANDA = [
        '{nama_siswa}' => 'Nama lengkap siswa',
        '{nis}' => 'NIS',
        '{nisn}' => 'NISN',
        '{kelas}' => 'Kelas siswa',
        '{nama_orang_tua}' => 'Nama ayah/ibu/wali',
        '{nama_madrasah}' => 'Nama madrasah',
        '{tahun_ajaran}' => 'Tahun ajaran aktif',
    ];

    /**
     * The sanitized body with every known placeholder filled; placeholders about a student stay visible until a
     * student is chosen.
     */
    public static function isi(?string $html, ?Siswa $siswa): string
    {
        $aman = (string) str((string) $html)->sanitizeHtml();

        return strtr($aman, array_map(
            fn (?string $nilai): string => e((string) $nilai),
            array_filter(self::nilai($siswa), fn (?string $nilai): bool => $nilai !== null),
        ));
    }

    /**
     * @return array<string, string|null>
     */
    private static function nilai(?Siswa $siswa): array
    {
        $siswa?->loadMissing(['kelas', 'ayah', 'ibu', 'wali']);

        return [
            '{nama_madrasah}' => (string) config('sekolah.nama'),
            '{tahun_ajaran}' => TahunAjaran::query()->where('is_aktif', true)->value('nama'),
            '{nama_siswa}' => $siswa?->nama_lengkap,
            '{nis}' => $siswa?->nis,
            '{nisn}' => $siswa?->nisn,
            '{kelas}' => $siswa?->kelas?->nama,
            '{nama_orang_tua}' => $siswa === null ? null : ($siswa->ayah->nama ?? $siswa->ibu->nama ?? $siswa->wali?->nama),
        ];
    }
}
