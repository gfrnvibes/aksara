<?php

namespace App\Services\Ppdb;

use App\Enums\JenisOrangTua;
use App\Enums\StatusPpdb;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Ppdb;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enters an accepted applicant into the Buku Induk: the student with their parents, placed in a first-grade class
 * for the registration's academic year, and their pas foto copied so it outlives the registration.
 */
final class PenerimaanSiswaBaru
{
    /**
     * Grade new students start in.
     */
    public const int TINGKAT_AWAL = 7;

    /**
     * @throws DomainException when the applicant cannot be entered; the message says why
     */
    public function jadikanSiswa(Ppdb $ppdb, string $nis, Kelas $kelas, CarbonInterface $tanggalDiterima): Siswa
    {
        $this->pastikanBisaDiterima($ppdb, $nis, $kelas);

        return DB::transaction(function () use ($ppdb, $nis, $kelas, $tanggalDiterima): Siswa {
            $siswa = Siswa::query()->create([
                'nis' => $nis,
                'nisn' => $ppdb->nisn,
                'nik' => $ppdb->nik,
                'status' => StatusSiswa::Aktif,
                'nama_lengkap' => $ppdb->nama_lengkap,
                'jenis_kelamin' => $ppdb->jenis_kelamin,
                'tempat_lahir' => $ppdb->tempat_lahir,
                'tanggal_lahir' => $ppdb->tanggal_lahir,
                'agama' => $ppdb->agama,
                'alamat' => $ppdb->alamat,
                'nomor_telepon' => $ppdb->nomor_telepon,
                'lulusan_dari' => $ppdb->asal_sekolah,
                'diterima_di_tingkat' => $kelas->tingkat,
                'tanggal_diterima' => $tanggalDiterima,
                'foto' => $this->salinPasFoto($ppdb),
            ]);

            $orangTua = [
                JenisOrangTua::Ayah->value => $ppdb->nama_ayah_kandung,
                JenisOrangTua::Ibu->value => $ppdb->nama_ibu_kandung,
                JenisOrangTua::Wali->value => $ppdb->nama_wali,
            ];

            foreach (array_filter($orangTua, filled(...)) as $jenis => $nama) {
                $siswa->orangTua()->create(['jenis' => $jenis, 'nama' => $nama]);
            }

            // The registration's year is written first, so the student's saved hook updates that row.
            RiwayatKelas::query()->create(['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $ppdb->tahun_ajaran_id, 'kelas_id' => $kelas->id]);
            $siswa->update(['kelas_id' => $kelas->id]);

            $ppdb->forceFill(['status' => StatusPpdb::JadiSiswa, 'siswa_id' => $siswa->id])->save();

            return $siswa;
        });
    }

    /**
     * @throws DomainException
     */
    private function pastikanBisaDiterima(Ppdb $ppdb, string $nis, Kelas $kelas): void
    {
        if ($ppdb->status !== StatusPpdb::Diterima) {
            throw new DomainException("{$ppdb->nama_lengkap} belum berstatus Diterima.");
        }

        if (! $ppdb->tahunAjaran->is_aktif) {
            throw new DomainException("Tahun ajaran {$ppdb->tahunAjaran->nama} belum aktif. Aktifkan dulu di menu Tahun Ajaran agar siswa baru tidak tercatat di kelas tahun ajaran berjalan.");
        }

        if ($kelas->tingkat !== self::TINGKAT_AWAL) {
            throw new DomainException("Kelas {$kelas->nama} bukan kelas ".self::TINGKAT_AWAL.'.');
        }

        if (Siswa::withTrashed()->where('nis', $nis)->exists()) {
            throw new DomainException("NIS {$nis} sudah dipakai siswa lain.");
        }

        $sudahAda = Siswa::withTrashed()
            ->where(fn (Builder $query) => $query->where('nisn', $ppdb->nisn)->orWhere('nik', $ppdb->nik))
            ->first();

        if ($sudahAda !== null) {
            throw new DomainException("{$ppdb->nama_lengkap} sudah ada di Buku Induk sebagai {$sudahAda->nis} - {$sudahAda->nama_lengkap} (NISN atau NIK sama).");
        }
    }

    /**
     * A copy of the applicant's pas foto for the student, so deleting the registration leaves the student's photo.
     */
    private function salinPasFoto(Ppdb $ppdb): ?string
    {
        $foto = $ppdb->berkas['foto'] ?? null;
        $disk = Storage::disk(Siswa::diskFoto());

        if (blank($foto) || ! $disk->exists($foto)) {
            return null;
        }

        $tujuan = 'foto-siswa/'.Str::uuid().'.'.pathinfo($foto, PATHINFO_EXTENSION);
        $disk->copy($foto, $tujuan);

        return $tujuan;
    }
}
