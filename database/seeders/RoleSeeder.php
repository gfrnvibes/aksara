<?php

namespace Database\Seeders;

use App\Filament\Guru\Pages\RekapHarian;
use App\Filament\Pages\BackupData;
use App\Filament\Pages\InputNilaiKelas;
use App\Filament\Pages\KenaikanKelas;
use App\Filament\Pages\LegerNilai;
use App\Filament\Pages\PenugasanPiket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The vice principal for curriculum: timetables, bell schedule, academic calendar, teaching assignments,
     * teachers' scheduling wishes, subjects, the question bank and exams, and a read-only view of teachers and classes.
     */
    public const string WK_KURIKULUM = 'wk_kurikulum';

    public const string KEPALA_MADRASAH = 'kepala_madrasah';

    public const string TATA_USAHA = 'tata_usaha';

    public const string WALI_KELAS = 'wali_kelas';

    public const string TIM_AKREDITASI = 'tim_akreditasi';

    public const string WK_KESISWAAN = 'wk_kesiswaan';

    public const string PEMBINA_ORGANISASI = 'pembina_organisasi';

    /**
     * @var list<string>
     */
    protected const array DIKELOLA_KURIKULUM = ['Jadwal', 'JamPelajaran', 'KalenderAkademik', 'PembagianMengajar', 'PermintaanJadwal', 'MataPelajaran', 'Soal', 'Ujian', 'AkreditasiDokumen', 'AkreditasiBukti'];

    /**
     * @var list<string>
     */
    protected const array DIKELOLA_TATA_USAHA = ['SuratMasuk', 'SuratKeluar', 'KopSurat', 'PenandaTangan', 'MutasiSiswa', 'Ijazah', 'Ppdb', 'GaleriKegiatan', 'KegiatanUjian', 'AkreditasiDokumen', 'AkreditasiBukti'];

    /**
     * @var list<string>
     */
    protected const array DIKELOLA_KESISWAAN = ['Ppdb', 'MutasiSiswa', 'Ekstrakurikuler', 'GaleriKegiatan', 'PelanggaranSiswa', 'PrestasiSiswa'];

    /**
     * Seed the Shield permissions plus the default roles.
     *
     * - super_admin: full access (handled by Shield).
     * - kepala_madrasah: read-only oversight across admin, finance and exam panels.
     * - operator: manages Buku Induk and master data and may restore from the trash, but cannot empty the
     *   trash or manage users, roles, backups or the activity log.
     * - tata_usaha: manages correspondence, archives, transfers, diplomas, PPDB and gallery, with read-only
     *   access to student, teacher, class and report card data.
     * - wk_kurikulum: see {@see self::WK_KURIKULUM}.
     * - bendahara: manages finance in the bendahara panel (no admin panel permissions).
     */
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        // The exam & accreditation panels' models, which Shield does not scan.
        foreach (['Soal', 'Ujian', 'KegiatanUjian', 'AkreditasiDokumen', 'AkreditasiBukti'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $aksi) {
                Permission::findOrCreate("{$aksi}:{$model}", 'web');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('bendahara', 'web');
        Role::findOrCreate(static::WALI_KELAS, 'web');
        Role::findOrCreate(static::PEMBINA_ORGANISASI, 'web');

        $superAdmin = Role::findOrCreate(config('filament-shield.super_admin.name'), 'web');
        $superAdmin->syncPermissions(Permission::all());

        Role::findOrCreate('operator', 'web')->syncPermissions(
            Permission::query()
                ->where('name', 'not like', '%:User')
                ->where('name', 'not like', '%:Role')
                ->where('name', 'not like', '%:Activity')
                ->where('name', 'not like', 'ForceDelete%')
                ->where('name', '!=', BackupData::IZIN)
                ->get(),
        );

        Role::findOrCreate(static::WK_KURIKULUM, 'web')->syncPermissions(
            Permission::query()
                ->where(function (Builder $query): void {
                    foreach (static::DIKELOLA_KURIKULUM as $model) {
                        $query->orWhere('name', 'like', "%:{$model}");
                    }

                    $query->orWhereIn('name', [
                        'ViewAny:Gtk', 'View:Gtk',
                        'ViewAny:Kelas', 'View:Kelas',
                        'ViewAny:Rapor', 'View:Rapor',
                        InputNilaiKelas::IZIN,
                        KenaikanKelas::IZIN,
                        LegerNilai::IZIN,
                    ]);
                })
                ->where('name', 'not like', 'ForceDelete%')
                ->get(),
        );

        Role::findOrCreate(static::KEPALA_MADRASAH, 'web')->syncPermissions(
            Permission::query()
                ->where(function (Builder $query): void {
                    $query->where('name', 'like', 'ViewAny:%')
                        ->orWhere('name', 'like', 'View:%');
                })
                ->where('name', '!=', BackupData::IZIN)
                ->get(),
        );

        Role::findOrCreate(static::TATA_USAHA, 'web')->syncPermissions(
            Permission::query()
                ->where(function (Builder $query): void {
                    foreach (static::DIKELOLA_TATA_USAHA as $model) {
                        $query->orWhere('name', 'like', "%:{$model}");
                    }

                    $query->orWhereIn('name', [
                        'ViewAny:Siswa', 'View:Siswa',
                        'ViewAny:Gtk', 'View:Gtk',
                        'ViewAny:Kelas', 'View:Kelas',
                        'ViewAny:Rapor', 'View:Rapor',
                        'ViewAny:TahunAjaran', 'View:TahunAjaran',
                    ]);
                })
                ->where('name', 'not like', 'ForceDelete%')
                ->get(),
        );

        Role::findOrCreate(static::WK_KESISWAAN, 'web')->syncPermissions(
            Permission::query()
                ->where(function (Builder $query): void {
                    foreach (static::DIKELOLA_KESISWAAN as $model) {
                        $query->orWhere('name', 'like', "%:{$model}");
                    }

                    $query->orWhereIn('name', [
                        'ViewAny:Siswa', 'View:Siswa',
                        'ViewAny:Gtk', 'View:Gtk',
                        'ViewAny:Kelas', 'View:Kelas',
                        'ViewAny:Rapor', 'View:Rapor',
                        'ViewAny:TahunAjaran', 'View:TahunAjaran',
                        'View:'.class_basename(PenugasanPiket::class),
                        'View:'.class_basename(RekapHarian::class),
                    ]);
                })
                ->where('name', 'not like', 'ForceDelete%')
                ->get(),
        );
        Role::findOrCreate(static::TIM_AKREDITASI, 'web')->syncPermissions(
            Permission::query()
                ->where(function (Builder $query): void {
                    $query->where('name', 'like', '%:AkreditasiDokumen')
                        ->orWhere('name', 'like', '%:AkreditasiBukti');
                })
                ->where('name', 'not like', 'ForceDelete%')
                ->get(),
        );
    }
}
