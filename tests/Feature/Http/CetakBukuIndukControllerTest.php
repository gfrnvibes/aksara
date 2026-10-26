<?php

use App\Enums\IsiDokumen;
use App\Enums\JenisOrangTua;
use App\Enums\Semester;
use App\Http\Controllers\CetakBukuIndukController;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\OrangTua;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
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

it('prints the homeroom note of each semester with the grades', function (string $format) {
    $siswa = Siswa::factory()->create();
    Rapor::factory()->for($siswa)->create(['semester' => Semester::Ganjil, 'catatan' => 'Ananda rajin dan santun.']);

    $respons = actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']))
        ->get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => 'nilai', ...($format === 'pdf' ? ['format' => 'pdf'] : [])]))
        ->assertOk();

    if ($format === 'pdf') {
        $respons->assertHeader('Content-Type', 'application/pdf');

        return;
    }

    $respons->assertSeeText(['Catatan Wali Kelas', 'Ananda rajin dan santun.']);
})->with(['cetak di browser' => ['html'], 'unduh pdf' => ['pdf']]);

it('prints the homeroom teacher and principal who signed that year', function () {
    config(['sekolah.tempat_tanda_tangan' => 'Cisurupan']);
    $siswa = Siswa::factory()->create();
    $kelas = Kelas::factory()->create(['wali_kelas' => 'Siti Aminah, S.Pd.', 'nip_wali_kelas' => '198001012005012001']);
    $tahunAjaran = TahunAjaran::factory()->create(['kepala_madrasah' => 'Drs. H. Ahmad Syafei', 'nip_kepala_madrasah' => '197001011995031001']);
    Rapor::factory()->for($siswa)->for($kelas)->for($tahunAjaran)->create([
        'semester' => Semester::Genap,
        'tanggal_keputusan' => '2026-06-20',
    ]);

    $kelas->update(['wali_kelas' => 'Wali Kelas Tahun Berikutnya']);

    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']))
        ->get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => 'nilai']))
        ->assertOk()
        ->assertSeeText(['Siti Aminah, S.Pd.', 'NIP. 198001012005012001', 'Drs. H. Ahmad Syafei', 'NIP. 197001011995031001', 'Cisurupan, 20 Juni 2026'])
        ->assertDontSeeText('Wali Kelas Tahun Berikutnya');
});

it('prints the class homeroom teacher for a report card saved before one was assigned', function () {
    $siswa = Siswa::factory()->create();
    $kelas = Kelas::factory()->create();
    Rapor::factory()->for($siswa)->for($kelas)->create(['semester' => Semester::Ganjil]);

    $kelas->update(['wali_kelas' => 'Akoh Barokah', 'nip_wali_kelas' => '198502022010011002']);

    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']))
        ->get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => 'nilai']))
        ->assertOk()
        ->assertSeeText(['Akoh Barokah', 'NIP. 198502022010011002']);
});

it('keeps a homeroom teacher typed on the report card instead of the class default', function () {
    $kelas = Kelas::factory()->create(['wali_kelas' => 'Wali Bawaan']);

    $rapor = Rapor::factory()->for($kelas)->create(['wali_kelas' => 'Wali Pengganti']);

    expect($rapor->fresh()->wali_kelas)->toBe('Wali Pengganti');
});

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
