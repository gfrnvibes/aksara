<?php

use App\Enums\JenisKelamin;
use App\Enums\JenisOrangTua;
use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Filament\Widgets\KelengkapanDataSiswa;
use App\Filament\Widgets\SiswaPerKelas;
use App\Filament\Widgets\StatistikBukuInduk;
use App\Models\Kelas;
use App\Models\OrangTua;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-10-28');
    actingAs(userWithPermissions(['ViewAny:Siswa']));
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
    $this->kelas = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A', 'wali_kelas' => 'Siti Aminah']);
});

it('counts the active students of each class by gender and their report cards this semester', function () {
    $ahmad = Siswa::factory()->for($this->kelas)->create(['jenis_kelamin' => JenisKelamin::LakiLaki]);
    Siswa::factory()->for($this->kelas)->create(['jenis_kelamin' => JenisKelamin::LakiLaki]);
    Siswa::factory()->for($this->kelas)->create(['jenis_kelamin' => JenisKelamin::Perempuan]);
    Siswa::factory()->for($this->kelas)->create(['status' => StatusSiswa::Pindah]);
    Rapor::factory()->for($ahmad)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);

    Livewire::test(SiswaPerKelas::class)
        ->assertCanSeeTableRecords([$this->kelas])
        ->assertTableColumnStateSet('jumlah_laki_laki', 2, $this->kelas)
        ->assertTableColumnStateSet('jumlah_perempuan', 1, $this->kelas)
        ->assertTableColumnStateSet('jumlah_siswa', 3, $this->kelas)
        ->assertTableColumnFormattedStateSet('jumlah_rapor', '1 / 3', $this->kelas);

    Livewire::test(StatistikBukuInduk::class)
        ->assertSeeText(['L 2 · P 1', '1 / 3', 'Semester 1 (Ganjil)']);
});

it('lists active students with missing data and what is missing', function () {
    $lengkap = Siswa::factory()->for($this->kelas)->create(['foto' => 'foto-siswa/a.jpg', 'alamat' => 'Cisurupan']);
    OrangTua::factory()->for($lengkap)->create(['jenis' => JenisOrangTua::Ayah]);
    OrangTua::factory()->for($lengkap)->create(['jenis' => JenisOrangTua::Ibu]);
    $belumLengkap = Siswa::factory()->for($this->kelas)->create(['nisn' => null, 'foto' => null, 'alamat' => 'Cisurupan']);
    OrangTua::factory()->for($belumLengkap)->create(['jenis' => JenisOrangTua::Ayah]);
    $lulus = Siswa::factory()->create(['status' => StatusSiswa::Lulus, 'foto' => null]);

    Livewire::test(KelengkapanDataSiswa::class)
        ->assertCanSeeTableRecords([$belumLengkap])
        ->assertCanNotSeeTableRecords([$lengkap, $lulus])
        ->assertTableColumnStateSet('kekurangan', ['NISN', 'Foto', 'Data ibu'], $belumLengkap);
});

it('hides the dashboard summaries from users who cannot see students', function () {
    actingAs(User::factory()->create());

    expect(StatistikBukuInduk::canView())->toBeFalse()
        ->and(SiswaPerKelas::canView())->toBeFalse()
        ->and(KelengkapanDataSiswa::canView())->toBeFalse();
});
