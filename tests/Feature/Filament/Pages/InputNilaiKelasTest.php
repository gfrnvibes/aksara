<?php

use App\Enums\Semester;
use App\Filament\Pages\InputNilaiKelas;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->kelas = Kelas::factory()->create(['nama' => 'VII-A']);
    $this->matematika = MataPelajaran::factory()->create(['nama' => 'Matematika', 'kkm' => 70]);

    actingAs(userWithPermissions([InputNilaiKelas::IZIN]));
});

/**
 * Open the page on the class, odd semester and Matematika.
 */
function bukaInputNilai(): mixed
{
    return Livewire::test(InputNilaiKelas::class)->fillForm([
        'tahun_ajaran_id' => test()->tahunAjaran->id,
        'semester' => Semester::Ganjil->value,
        'kelas_id' => test()->kelas->id,
        'mata_pelajaran_id' => test()->matematika->id,
    ]);
}

/**
 * The repeater key of the student's row.
 */
function kunciBaris(mixed $halaman, Siswa $siswa): string
{
    return collect($halaman->get('data.nilai'))->search(fn (array $baris): bool => $baris['siswa_id'] === $siswa->id);
}

it('is hidden from users without the class grade entry permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(InputNilaiKelas::class)->assertForbidden();
});

it('lists the members of the class that year with the grades already entered', function () {
    $sudahDinilai = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Ahmad']);
    $belumDinilai = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Budi']);
    $dulunyaDiKelasIni = Siswa::factory()->create(['nama_lengkap' => 'Citra']);
    RiwayatKelas::factory()->for($dulunyaDiKelasIni)->for(TahunAjaran::factory())->for($this->kelas)->create();
    $rapor = Rapor::factory()->for($sudahDinilai)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);
    NilaiRapor::factory()->for($rapor)->for($this->matematika)->create(['kkm' => 72, 'nilai_pengetahuan' => 88, 'nilai_keterampilan' => 80, 'sikap' => 'B']);

    $halaman = bukaInputNilai()->assertSchemaStateSet(['kkm' => 72]);

    expect(collect($halaman->get('data.nilai'))->map(fn (array $baris): array => [$baris['siswa_id'], $baris['nilai_pengetahuan'], $baris['sikap']])->values()->all())
        ->toBe([
            [$sudahDinilai->id, 88, 'B'],
            [$belumDinilai->id, null, null],
        ]);
});

it('saves the grades of the class and creates the missing report cards', function () {
    $sudahPunyaRapor = Siswa::factory()->for($this->kelas)->create();
    $belumPunyaRapor = Siswa::factory()->for($this->kelas)->create();
    $tidakDiisi = Siswa::factory()->for($this->kelas)->create();
    Rapor::factory()->for($sudahPunyaRapor)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);

    $halaman = bukaInputNilai();
    $halaman
        ->set('data.nilai.'.kunciBaris($halaman, $sudahPunyaRapor).'.nilai_pengetahuan', 95)
        ->set('data.nilai.'.kunciBaris($halaman, $belumPunyaRapor).'.nilai_pengetahuan', 60)
        ->set('data.nilai.'.kunciBaris($halaman, $belumPunyaRapor).'.nilai_keterampilan', 75)
        ->callAction('simpanNilai')
        ->assertNotified('Nilai tersimpan');

    $raporBaru = Rapor::query()->where('siswa_id', $belumPunyaRapor->id)->sole();
    $nilaiBaru = $raporBaru->nilaiRapor()->sole();

    expect($sudahPunyaRapor->rapor()->sole()->nilaiRapor()->sole())
        ->nilai_pengetahuan->toBe(95)
        ->predikat_pengetahuan->toBe('A')
        ->and($raporBaru->kelas_id)->toBe($this->kelas->id)
        ->and($raporBaru->semester)->toBe(Semester::Ganjil)
        ->and($nilaiBaru)
        ->kkm->toBe(70)
        ->predikat_pengetahuan->toBe('D')
        ->nilai_keterampilan->toBe(75)
        ->and($tidakDiisi->rapor()->exists())->toBeFalse();
});

it('applies a KKM changed through its modal to every saved grade', function () {
    $siswa = Siswa::factory()->for($this->kelas)->create();

    $halaman = bukaInputNilai();
    $halaman
        ->assertSchemaComponentExists('kkm', checkComponentUsing: fn ($field): bool => $field->isReadOnly())
        ->callAction(TestAction::make('ubahKkm')->schemaComponent('kkm', schema: 'form'), ['kkm' => 80])
        ->set('data.nilai.'.kunciBaris($halaman, $siswa).'.nilai_pengetahuan', 78)
        ->callAction('simpanNilai');

    expect(NilaiRapor::query()->sole())->kkm->toBe(80)->predikat_pengetahuan->toBe('D');
});

it('rejects scores above 100', function () {
    $siswa = Siswa::factory()->for($this->kelas)->create();

    $halaman = bukaInputNilai();
    $halaman
        ->set('data.nilai.'.kunciBaris($halaman, $siswa).'.nilai_pengetahuan', 101)
        ->callAction('simpanNilai')
        ->assertHasErrors(['data.nilai.'.kunciBaris($halaman, $siswa).'.nilai_pengetahuan' => 'max']);

    expect(NilaiRapor::query()->exists())->toBeFalse();
});

it('does not grade a student whose report card is in the trash', function () {
    $siswa = Siswa::factory()->for($this->kelas)->create();
    Rapor::factory()->for($siswa)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil])->delete();

    $halaman = bukaInputNilai();
    $kunci = kunciBaris($halaman, $siswa);

    expect($halaman->get("data.nilai.{$kunci}.terkunci"))->toBeTrue();

    $halaman
        ->set("data.nilai.{$kunci}.terkunci", false)
        ->set("data.nilai.{$kunci}.nilai_pengetahuan", 90)
        ->callAction('simpanNilai');

    expect(NilaiRapor::query()->exists())->toBeFalse();
});

it('ignores a row pointed at a student outside the class', function () {
    $siswa = Siswa::factory()->for($this->kelas)->create();
    $kelasLain = Siswa::factory()->for(Kelas::factory())->create();

    $halaman = bukaInputNilai();
    $kunci = kunciBaris($halaman, $siswa);

    $halaman
        ->set("data.nilai.{$kunci}.siswa_id", $kelasLain->id)
        ->set("data.nilai.{$kunci}.nilai_pengetahuan", 90)
        ->callAction('simpanNilai');

    expect(Rapor::query()->exists())->toBeFalse();
});
