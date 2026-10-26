<?php

use App\Enums\Semester;
use App\Filament\Pages\LegerNilai;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->kelas = Kelas::factory()->create(['nama' => 'VIII-B']);
    $this->siswa = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Ahmad Fauzi']);
    $rapor = Rapor::factory()->for($this->siswa)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);
    NilaiRapor::factory()->for($rapor)->for(MataPelajaran::factory()->create(['nama' => 'Matematika']))->create(['nilai_pengetahuan' => 87, 'nilai_keterampilan' => 83]);

    actingAs(userWithPermissions([LegerNilai::IZIN]));
});

/**
 * Open the leger of the class for the odd semester.
 */
function bukaLeger(): mixed
{
    return Livewire::test(LegerNilai::class)->fillForm([
        'tahun_ajaran_id' => test()->tahunAjaran->id,
        'semester' => Semester::Ganjil->value,
        'kelas_id' => test()->kelas->id,
    ]);
}

it('is hidden from users without the leger permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(LegerNilai::class)->assertForbidden();
});

it('shows the grades, average and rank of the chosen class', function () {
    bukaLeger()
        ->assertSeeText(['Ahmad Fauzi', 'Matematika', '87', '83', '85,00'])
        ->assertActionVisible('unduhExcel');
});

it('asks for a class before showing or downloading anything', function () {
    Livewire::test(LegerNilai::class)
        ->assertSeeText('Pilih tahun ajaran, semester, dan kelas')
        ->assertActionHidden('unduhExcel');
});

it('downloads the leger as Excel and PDF', function (string $aksi, string $ekstensi) {
    $this->tahunAjaran->update(['nama' => '2025/2026']);

    bukaLeger()
        ->callAction($aksi)
        ->assertFileDownloaded("leger-viii-b-20252026-semester-ganjil.{$ekstensi}");
})->with([
    'excel' => ['unduhExcel', 'xlsx'],
    'pdf' => ['unduhPdf', 'pdf'],
]);
