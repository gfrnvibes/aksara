<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a registration stands: checked by the committee, then accepted, held in reserve or turned down, and
 * finally entered into the Buku Induk as a student.
 */
enum StatusPpdb: string implements HasColor, HasLabel
{
    case Menunggu = 'menunggu';
    case Terverifikasi = 'terverifikasi';
    case Diterima = 'diterima';
    case Cadangan = 'cadangan';
    case Ditolak = 'ditolak';
    case JadiSiswa = 'jadi_siswa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Verifikasi',
            self::Terverifikasi => 'Terverifikasi',
            self::Diterima => 'Diterima',
            self::Cadangan => 'Cadangan',
            self::Ditolak => 'Ditolak',
            self::JadiSiswa => 'Sudah Jadi Siswa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Menunggu => 'gray',
            self::Terverifikasi => 'info',
            self::Diterima => 'success',
            self::Cadangan => 'warning',
            self::Ditolak => 'danger',
            self::JadiSiswa => 'primary',
        };
    }

    /**
     * The statuses a registration may move to from this one. Only an accepted applicant becomes a student, and
     * that happens through the Buku Induk entry, never by changing the status by hand.
     *
     * @return list<self>
     */
    public function bisaMenjadi(): array
    {
        return match ($this) {
            self::Menunggu => [self::Terverifikasi, self::Ditolak],
            self::Terverifikasi => [self::Menunggu, self::Diterima, self::Cadangan, self::Ditolak],
            self::Cadangan => [self::Terverifikasi, self::Diterima, self::Ditolak],
            self::Diterima, self::Ditolak => [self::Terverifikasi],
            self::JadiSiswa => [],
        };
    }

    /**
     * What the applicant reads when checking their registration on the public page.
     */
    public function pesanUntukPendaftar(): string
    {
        return match ($this) {
            self::Menunggu => 'Pendaftaran sudah kami terima dan sedang menunggu verifikasi panitia.',
            self::Terverifikasi => 'Data dan berkas sudah diverifikasi. Silakan tunggu pengumuman hasil seleksi.',
            self::Diterima, self::JadiSiswa => 'Selamat, Anda diterima. Silakan lakukan daftar ulang sesuai jadwal dari madrasah.',
            self::Cadangan => 'Anda masuk daftar cadangan. Panitia akan menghubungi bila ada kuota tambahan.',
            self::Ditolak => 'Mohon maaf, Anda belum dapat diterima pada tahun ajaran ini.',
        };
    }
}
