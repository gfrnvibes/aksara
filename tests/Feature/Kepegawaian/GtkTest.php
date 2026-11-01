<?php

use App\Filament\Resources\Gtks\Pages\ManageGtks;
use App\Filament\Resources\PembagianMengajars\Pages\ManagePembagianMengajars;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Kepegawaian\AkunGtk;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    actingAs(User::factory()->create()->assignRole('operator'));
});

it('stores phone numbers as digits starting with 0', function () {
    $gtk = Gtk::factory()->create(['nomor_hp' => '+62 812-3456-7890']);

    expect($gtk->nomor_hp)->toBe('081234567890');
});

it('copies the homeroom teacher chosen from the GTK data onto the class, and follows later corrections', function () {
    $gtk = Gtk::factory()->create(['nama' => 'Siti Aminah, S.Pd.', 'nip' => '198501012010012001']);
    $kelas = Kelas::factory()->create(['wali_kelas' => 'Nama Lama']);

    $kelas->update(['wali_kelas_id' => $gtk->id]);

    expect($kelas->refresh())
        ->wali_kelas->toBe('Siti Aminah, S.Pd.')
        ->nip_wali_kelas->toBe('198501012010012001');

    $gtk->update(['nama' => 'Siti Aminah, S.Pd., M.Pd.']);

    expect($kelas->refresh()->wali_kelas)->toBe('Siti Aminah, S.Pd., M.Pd.');
});

it('creates login accounts with a starting password the teacher must change', function () {
    $gtk = Gtk::factory()->create(['email' => 'siti@example.com']);

    Livewire::test(ManageGtks::class)
        ->callAction(TestAction::make('buatAkun')->table($gtk), ['password_awal' => 'rahasia123'])
        ->assertHasNoActionErrors();

    $user = $gtk->refresh()->user;

    expect($user)
        ->name->toBe($gtk->nama)
        ->email->toBe('siti@example.com')
        ->wajib_ganti_password->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('creates accounts in bulk and skips GTK with nothing to sign in with', function () {
    $siapAkun = Gtk::factory()->create();
    $tanpaIdentitas = Gtk::factory()->create(['nip' => null, 'nuptk' => null, 'nomor_hp' => null, 'email' => null]);
    $sudahPunya = Gtk::factory()->denganAkun()->create();

    Livewire::test(ManageGtks::class)
        ->selectTableRecords([$siapAkun, $tanpaIdentitas, $sudahPunya])
        ->callAction(TestAction::make('buatAkunMassal')->table()->bulk(), ['password_awal' => 'rahasia123']);

    expect($siapAkun->refresh()->user_id)->not->toBeNull()
        ->and($tanpaIdentitas->refresh()->user_id)->toBeNull()
        ->and(User::query()->count())->toBe(3);
});

it('lets an operator reset teacher accounts but not accounts holding an admin role', function () {
    $guru = Gtk::factory()->denganAkun()->create();
    $admin = Gtk::factory()->denganAkun()->create();
    $admin->user->assignRole('operator');

    expect(AkunGtk::bolehDireset($guru, auth()->user()))->toBeTrue()
        ->and(AkunGtk::bolehDireset($admin, auth()->user()))->toBeFalse();

    Livewire::test(ManageGtks::class)
        ->assertActionVisible(TestAction::make('resetPassword')->table($guru))
        ->assertActionHidden(TestAction::make('resetPassword')->table($admin));
});

it('assigns a subject to a teacher in several classes at once and skips classes that already have a teacher', function () {
    $tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $guru = Gtk::factory()->create();
    $guruLain = Gtk::factory()->create();
    $mapel = MataPelajaran::factory()->create();
    [$kelasA, $kelasB] = Kelas::factory()->count(2)->create();
    PembagianMengajar::factory()->for($tahunAjaran)->for($kelasB)->for($mapel)->for($guruLain)->create();

    Livewire::test(ManagePembagianMengajars::class)
        ->callAction('tambah', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'gtk_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_ids' => [$kelasA->id, $kelasB->id],
            'jp_per_minggu' => 4,
        ])
        ->assertHasNoActionErrors();

    expect(PembagianMengajar::query()->where('kelas_id', $kelasA->id)->first())
        ->gtk_id->toBe($guru->id)
        ->jp_per_minggu->toBe(4)
        ->and(PembagianMengajar::query()->where('kelas_id', $kelasB->id)->value('gtk_id'))->toBe($guruLain->id);
});

it('copies last year\'s assignments of active teachers into a new year', function () {
    $tahunLalu = TahunAjaran::factory()->create(['nama' => '2025/2026']);
    $tahunIni = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
    $tetap = PembagianMengajar::factory()->for($tahunLalu)->create();
    PembagianMengajar::factory()->for($tahunLalu)->for(Gtk::factory()->state(['is_aktif' => false]))->create();

    Livewire::test(ManagePembagianMengajars::class)
        ->callAction('salin', ['dari_tahun_ajaran_id' => $tahunLalu->id, 'ke_tahun_ajaran_id' => $tahunIni->id])
        ->assertHasNoActionErrors();

    expect(PembagianMengajar::query()->where('tahun_ajaran_id', $tahunIni->id)->get())
        ->toHaveCount(1)
        ->first()->gtk_id->toBe($tetap->gtk_id);
});

it('refuses to delete a GTK who still has teaching assignments', function () {
    $pembagian = PembagianMengajar::factory()->create();

    expect($pembagian->gtk->delete())->toBeFalse()
        ->and(Gtk::query()->whereKey($pembagian->gtk_id)->exists())->toBeTrue();
});

it('keeps a 16-digit NIK entered in the form as text', function () {
    Livewire::test(ManageGtks::class)
        ->callAction('create', [
            'nama' => 'Ahmad Fauzi, S.Pd.',
            'jenis_kelamin' => 'L',
            'nik' => '3205010101900001',
            'jenis_gtk' => 'guru',
            'status_kepegawaian' => 'gtt',
        ])
        ->assertHasNoActionErrors();

    expect(Gtk::query()->sole()->nik)->toBe('3205010101900001');
});

it('rejects a NIK that is not 16 digits', function () {
    Livewire::test(ManageGtks::class)
        ->callAction('create', [
            'nama' => 'Ahmad Fauzi, S.Pd.',
            'jenis_kelamin' => 'L',
            'nik' => '3205-0101',
            'jenis_gtk' => 'guru',
            'status_kepegawaian' => 'gtt',
        ])
        ->assertHasActionErrors(['nik']);
});
