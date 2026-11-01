<?php

use App\Enums\Semester;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Guru\Pages\InputNilai;
use App\Filament\Guru\Pages\RaporKelasSaya;
use App\Models\Ekstrakurikuler;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\PembagianMengajar;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('guru');

    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->gtk = Gtk::factory()->denganAkun()->create(['nip' => '198501012010012001', 'nuptk' => '1234567890123456', 'nomor_hp' => '081234567890']);
    $this->guru = $this->gtk->user;
});

it('signs teachers in with their NIP, NUPTK or phone number', function (string $login) {
    $this->guru->update(['password' => 'rahasia123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $login, 'password' => 'rahasia123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->guru);
})->with([
    'NIP' => '198501012010012001',
    'NUPTK' => '1234567890123456',
    'No. HP dengan kode negara' => '+62 812 3456 7890',
]);

it('rejects a wrong password', function () {
    $this->guru->update(['password' => 'rahasia123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => '198501012010012001', 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('opens the teacher panel only to users linked to an active GTK', function () {
    actingAs($this->guru)->get(filament()->getUrl())->assertOk();
    actingAs($this->guru)->get(Filament::getPanel('admin')->getUrl())->assertForbidden();

    $this->gtk->update(['is_aktif' => false]);
    actingAs($this->guru->refresh())->get(filament()->getUrl())->assertForbidden();

    actingAs(User::factory()->create())->get(filament()->getUrl())->assertForbidden();
});

it('sends a teacher with the starting password to the account page until they choose their own', function () {
    $this->guru->update(['password' => 'awal1234', 'wajib_ganti_password' => true]);

    actingAs($this->guru)->get(InputNilai::getUrl())->assertRedirect(EditProfile::getUrl());

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'PasswordBaru123',
            'passwordConfirmation' => 'PasswordBaru123',
            'currentPassword' => 'awal1234',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->guru->refresh()->wajib_ganti_password)->toBeFalse();
    $this->flushSession();
    actingAs($this->guru)->get(InputNilai::getUrl())->assertOk();
});

it('offers only the classes and subjects assigned to the teacher', function () {
    [$kelasDiajar, $kelasLain] = Kelas::factory()->count(2)->create();
    [$mapelDiajar, $mapelLain] = MataPelajaran::factory()->count(2)->create();
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelasDiajar)->for($mapelDiajar)->for($this->gtk)->create();
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelasDiajar)->for($mapelLain)->create();
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelasLain)->for($mapelDiajar)->create();

    actingAs($this->guru);

    Livewire::test(InputNilai::class)
        ->fillForm(['tahun_ajaran_id' => $this->tahunAjaran->id, 'kelas_id' => $kelasDiajar->id])
        ->assertFormFieldExists('kelas_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$kelasDiajar->id])
        ->assertFormFieldExists('mata_pelajaran_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$mapelDiajar->id]);
});

it('opens on the grade sheet of a class and subject from the dashboard link and saves it', function () {
    $kelas = Kelas::factory()->create();
    $mapel = MataPelajaran::factory()->create(['kkm' => 70]);
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelas)->for($mapel)->for($this->gtk)->create();
    $siswa = Siswa::factory()->for($kelas)->create();

    actingAs($this->guru);

    $halaman = Livewire::withQueryParams(['kelas' => $kelas->id, 'mapel' => $mapel->id])->test(InputNilai::class);
    $kunci = collect($halaman->get('data.nilai'))->search(fn (array $baris): bool => $baris['siswa_id'] === $siswa->id);

    expect($kunci)->not->toBeFalse();

    $halaman->set("data.nilai.{$kunci}.nilai_pengetahuan", 88)->callAction('simpanNilai');

    expect(NilaiRapor::query()->where('mata_pelajaran_id', $mapel->id)->value('nilai_pengetahuan'))->toBe(88);
});

it('refuses to save grades of a subject the teacher is not assigned', function () {
    $kelas = Kelas::factory()->create();
    $mapel = MataPelajaran::factory()->create();
    Siswa::factory()->for($kelas)->create();

    actingAs($this->guru);

    Livewire::withQueryParams(['kelas' => $kelas->id, 'mapel' => $mapel->id])
        ->test(InputNilai::class)
        ->assertSet('data.nilai', [])
        ->call('simpan')
        ->assertHasFormErrors(['kelas_id', 'mata_pelajaran_id']);

    expect(NilaiRapor::query()->count())->toBe(0);
});

it('shows the report card page only to homeroom teachers', function () {
    actingAs($this->guru)->get(RaporKelasSaya::getUrl())->assertForbidden();

    Kelas::factory()->create(['wali_kelas_id' => $this->gtk->id]);

    $this->get(RaporKelasSaya::getUrl())->assertOk();
});

it('lets the homeroom teacher fill in and print the report cards of their class', function () {
    $kelas = Kelas::factory()->create(['wali_kelas_id' => $this->gtk->id]);
    $siswa = Siswa::factory()->for($kelas)->create();
    $bukanAnggota = Siswa::factory()->create();
    $pramuka = Ekstrakurikuler::factory()->create(['nama' => 'Pramuka']);

    actingAs($this->guru);

    Livewire::test(RaporKelasSaya::class)
        ->assertCanSeeTableRecords([$siswa])
        ->assertCanNotSeeTableRecords([$bukanAnggota])
        ->callAction(TestAction::make('isiRapor')->table($siswa), [
            'sakit' => 2,
            'izin' => 1,
            'tanpa_keterangan' => 0,
            'nilaiEkstrakurikuler' => [['ekstrakurikuler_id' => $pramuka->id, 'nilai' => 'A', 'keterangan' => null]],
            'catatan' => 'Pertahankan semangat belajarmu.',
        ])
        ->assertHasNoActionErrors();

    $rapor = Rapor::query()->where('siswa_id', $siswa->id)->sole();

    expect($rapor)
        ->kelas_id->toBe($kelas->id)
        ->semester->toBe(Semester::berdasarkanTanggal(now()))
        ->sakit->toBe(2)
        ->catatan->toBe('Pertahankan semangat belajarmu.')
        ->and($rapor->nilaiEkstrakurikuler()->pluck('ekstrakurikuler_id')->all())->toBe([$pramuka->id]);

    $this->get(route('rapor.cetak', $rapor))->assertOk();
});

it('keeps report cards of other classes from the homeroom teacher', function () {
    Kelas::factory()->create(['wali_kelas_id' => $this->gtk->id]);
    $raporLain = Rapor::factory()->create();

    actingAs($this->guru)->get(route('rapor.cetak', $raporLain))->assertForbidden();
});
