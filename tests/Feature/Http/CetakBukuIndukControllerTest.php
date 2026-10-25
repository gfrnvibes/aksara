<?php

use App\Enums\IsiDokumen;
use App\Enums\JenisOrangTua;
use App\Enums\Semester;
use App\Http\Controllers\CetakBukuIndukController;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\OrangTua;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to the login page', function () {
    $siswa = Siswa::factory()->create();

    get(route('siswa.cetak', $siswa))->assertRedirect(route('login'));
});

it('forbids users without permission to view students', function () {
    $siswa = Siswa::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('siswa.cetak', $siswa))
        ->assertForbidden();
});

it('renders the student sheet with grades for each semester', function () {
    $siswa = Siswa::factory()->create(['nama_lengkap' => 'Ahmad Fauzi']);
    OrangTua::factory()->for($siswa)->create(['jenis' => JenisOrangTua::Ayah, 'nama' => 'Budi Santoso']);
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika']);
    $rapor = Rapor::factory()->for($siswa)->create(['semester' => Semester::Genap]);
    NilaiRapor::factory()->for($rapor)->for($matematika)->create(['nilai_pengetahuan' => 87]);

    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']))
        ->get(route('siswa.cetak', $siswa))
        ->assertOk()
        ->assertSeeText(['Ahmad Fauzi', 'Budi Santoso', $rapor->tahunAjaran->nama, 'Matematika', '87']);
});

it('prints only the requested part of the Buku Induk', function (string $isi, bool $adaBiodata, bool $adaNilai) {
    $siswa = Siswa::factory()->create();
    Rapor::factory()->for($siswa)->create();

    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']))
        ->get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => $isi]))
        ->assertOk()
        ->{$adaBiodata ? 'assertSeeText' : 'assertDontSeeText'}('LEMBAR BUKU INDUK SISWA')
        ->{$adaNilai ? 'assertSeeText' : 'assertDontSeeText'}('DATA NILAI SISWA');
})->with([
    'data siswa' => ['siswa', true, false],
    'data nilai' => ['nilai', false, true],
    'keduanya' => ['keduanya', true, true],
]);

it('forbids printing grades without permission to view grades', function () {
    $siswa = Siswa::factory()->create();

    actingAs(userWithPermissions(['View:Siswa']))
        ->get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => 'nilai']))
        ->assertForbidden();
});

it('downloads the Buku Induk of one student as PDF', function () {
    $siswa = Siswa::factory()->create();
    Rapor::factory()->for($siswa)->create();

    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']))
        ->get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => 'keduanya', 'format' => 'pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload();
});

it('hides a mass print request from other users', function () {
    $pemilik = userWithPermissions(['ViewAny:Siswa']);
    $siswa = Siswa::factory()->create();

    actingAs($pemilik);
    $token = CetakBukuIndukController::siapkanCetakMassal([$siswa->id], IsiDokumen::Siswa);

    $this->get(route('buku-induk.cetak-massal', $token))->assertOk()->assertSeeText($siswa->nama_lengkap);

    actingAs(userWithPermissions(['ViewAny:Siswa']))
        ->get(route('buku-induk.cetak-massal', $token))
        ->assertNotFound();
});
