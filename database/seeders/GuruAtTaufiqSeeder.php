<?php

namespace Database\Seeders;

use App\Enums\JenisGtk;
use App\Enums\JenisKelamin;
use App\Enums\KelompokMapel;
use App\Enums\StatusKepegawaian;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The teachers of MTs At-Taufiq Cisurupan and what they teach, from the 2026/2027 odd-semester timetable, seeded
 * as the teaching assignments of the active academic year for classes VII, VIII and IX.
 *
 * Safe to run again: teachers are matched by name (ignoring academic titles), subjects by name, and assignments by
 * class and subject, so nothing is duplicated. Gender and employment status are not on the timetable; check them
 * in Data GTK.
 */
class GuruAtTaufiqSeeder extends Seeder
{
    /**
     * Subjects missing from the master data, as name => [group, order, abbreviation].
     *
     * @var array<string, array{0: KelompokMapel, 1: int, 2: string}>
     */
    protected const array MAPEL_TAMBAHAN = [
        'Informatika' => [KelompokMapel::A, 12, 'INF'],
        'Tahfidz' => [KelompokMapel::B, 4, 'TAHFIDZ'],
        'Bahasa Sunda' => [KelompokMapel::B, 5, 'B. SUNDA'],
    ];

    /**
     * Teacher => [gender, [subject => JP for VII, VIII, IX]].
     *
     * @var array<string, array{0: JenisKelamin, 1: array<string, array{0: int, 1: int, 2: int}>}>
     */
    protected const array PEMBAGIAN = [
        'Sofwan Munawar, S.Pd.I.' => [JenisKelamin::LakiLaki, [
            'Al-Qur\'an Hadist' => [2, 2, 2],
            'Sejarah Kebudayaan Islam' => [0, 2, 2],
        ]],
        'Enur Hasanukri, S.Pd.I.' => [JenisKelamin::LakiLaki, [
            'Fiqih' => [2, 2, 2],
        ]],
        'Dodi Rahman, S.Pd.I' => [JenisKelamin::LakiLaki, [
            'Aqidah Akhlak' => [2, 2, 2],
            'Sejarah Kebudayaan Islam' => [2, 0, 0],
            'Bahasa Indonesia' => [0, 0, 6],
        ]],
        'Dede Siti Wahidah, S.Pd.' => [JenisKelamin::Perempuan, [
            'Bahasa Inggris' => [4, 4, 4],
        ]],
        'Ahmad Wahidin, S.Pd.' => [JenisKelamin::LakiLaki, [
            'Pendidikan Pancasila dan Kewarganegaraan' => [3, 3, 3],
        ]],
        'Irma Nurmalasari, S.Pd.' => [JenisKelamin::Perempuan, [
            'Ilmu Pengetahuan Alam' => [0, 5, 5],
        ]],
        'Abdul Wahab, S.Pd.' => [JenisKelamin::LakiLaki, [
            'Matematika' => [5, 5, 5],
        ]],
        'Evi Alfikin, S.Pd.' => [JenisKelamin::Perempuan, [
            'Ilmu Pengetahuan Sosial' => [4, 3, 4],
        ]],
        'Ust. Akoh Barokah' => [JenisKelamin::LakiLaki, [
            'Tahfidz' => [2, 2, 2],
        ]],
        'Mu\'min Mustawan Jamil, S.Pd.I.' => [JenisKelamin::LakiLaki, [
            'Seni Budaya' => [2, 2, 2],
        ]],
        'Ahmad Nurhalim, S.Kom.' => [JenisKelamin::LakiLaki, [
            'Informatika' => [2, 2, 2],
        ]],
        'Ade Hasan Mustopa, S.Pd.' => [JenisKelamin::LakiLaki, [
            'Bahasa Arab' => [3, 3, 3],
            'Bahasa Indonesia' => [0, 6, 0],
        ]],
        'Gufron Nurjalil, S.Kom.' => [JenisKelamin::LakiLaki, [
            'Bahasa Sunda' => [2, 2, 2],
            'Bahasa Indonesia' => [6, 0, 0],
            'Ilmu Pengetahuan Alam' => [5, 0, 0],
        ]],
        'Asep Lukmanul Hakim' => [JenisKelamin::LakiLaki, [
            'Pendidikan Jasmani, Olahraga dan Kesehatan' => [2, 2, 2],
        ]],
    ];

    public function run(): void
    {
        $tahunAjaran = TahunAjaran::query()->where('is_aktif', true)->first();

        if ($tahunAjaran === null) {
            $this->command?->warn('Tidak ada tahun ajaran aktif; pembagian mengajar tidak dibuat.');

            return;
        }

        foreach (static::MAPEL_TAMBAHAN as $nama => [$kelompok, $urutan, $singkatan]) {
            MataPelajaran::query()->firstOrCreate(
                ['nama' => $nama],
                ['kelompok' => $kelompok, 'urutan' => $urutan, 'kkm' => 75, 'singkatan' => $singkatan],
            );
        }

        $kelas = collect([7 => 'VII', 8 => 'VIII', 9 => 'IX'])
            ->map(fn (string $nama, int $tingkat): Kelas => Kelas::query()->firstOrCreate(['nama' => $nama], ['tingkat' => $tingkat]))
            ->values();
        $mapel = MataPelajaran::query()->pluck('id', 'nama');

        foreach (static::PEMBAGIAN as $nama => [$jenisKelamin, $daftarMapel]) {
            $gtk = $this->cariGtk($nama) ?? Gtk::query()->create([
                'nama' => $nama,
                'jenis_kelamin' => $jenisKelamin,
                'jenis_gtk' => JenisGtk::Guru,
                'status_kepegawaian' => StatusKepegawaian::TetapYayasan,
            ]);

            foreach ($daftarMapel as $namaMapel => $jpPerKelas) {
                foreach ($jpPerKelas as $i => $jp) {
                    if ($jp === 0) {
                        continue;
                    }

                    PembagianMengajar::query()->updateOrCreate(
                        ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas[$i]->id, 'mata_pelajaran_id' => $mapel[$namaMapel]],
                        ['gtk_id' => $gtk->id, 'jp_per_minggu' => $jp],
                    );
                }
            }
        }
    }

    /**
     * The GTK with the same name, ignoring academic titles, spacing and case: "Gufron Nurjalil, S. Kom." matches
     * "Gufron Nurjalil, S.Kom.".
     */
    protected function cariGtk(string $nama): ?Gtk
    {
        $kunci = static::kunciNama($nama);

        return Gtk::query()->get()->first(fn (Gtk $gtk): bool => static::kunciNama($gtk->nama) === $kunci);
    }

    protected static function kunciNama(string $nama): string
    {
        return Str::of($nama)->before(',')->replaceMatches('/^(ust|ustadz|ustadzah|hj?|drs?)\.?\s+/i', '')->lower()->squish()->toString();
    }
}
