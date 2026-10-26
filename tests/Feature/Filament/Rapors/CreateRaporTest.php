<?php

use App\Enums\Semester;
use App\Filament\Resources\Rapors\Pages\CreateRapor;
use App\Filament\Resources\Rapors\Pages\EditRapor;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions(['ViewAny:Rapor', 'View:Rapor', 'Create:Rapor', 'Update:Rapor']));
});

it('prefills every active subject with its KKM', function () {
    $matematika = MataPelajaran::factory()->create(['kkm' => 70]);
    MataPelajaran::factory()->create(['is_aktif' => false]);

    $nilaiRapor = array_values(Livewire::test(CreateRapor::class)->get('data.nilaiRapor'));

    expect($nilaiRapor)->toHaveCount(1)
        ->and($nilaiRapor[0]['mata_pelajaran_id'])->toBe($matematika->id)
        ->and($nilaiRapor[0]['kkm'])->toBe(70);
});

it('changes the KKM of one subject only through its edit modal', function () {
    MataPelajaran::factory()->create(['kkm' => 70]);

    $halaman = Livewire::test(CreateRapor::class);
    $kunciBaris = array_key_first($halaman->get('data.nilaiRapor'));

    $halaman
        ->assertFormFieldExists("nilaiRapor.{$kunciBaris}.kkm", fn (TextInput $field): bool => $field->isReadOnly())
        ->callAction(TestAction::make('ubahKkm')->schemaComponent("nilaiRapor.{$kunciBaris}.kkm", schema: 'form'), ['kkm' => 75])
        ->assertHasNoFormErrors();

    expect($halaman->get("data.nilaiRapor.{$kunciBaris}.kkm"))->toBe(75);
});

it('rejects a KKM outside 1 to 100 in the edit modal', function () {
    MataPelajaran::factory()->create(['kkm' => 70]);

    $halaman = Livewire::test(CreateRapor::class);
    $kunciBaris = array_key_first($halaman->get('data.nilaiRapor'));

    $halaman
        ->callAction(TestAction::make('ubahKkm')->schemaComponent("nilaiRapor.{$kunciBaris}.kkm", schema: 'form'), ['kkm' => 101])
        ->assertHasFormErrors(['kkm' => 'max']);

    expect($halaman->get("data.nilaiRapor.{$kunciBaris}.kkm"))->toBe(70);
});

it('lists every student with their class before anything is typed', function () {
    $kelas = Kelas::factory()->create(['nama' => 'VIII-B']);
    $ahmad = Siswa::factory()->for($kelas)->create(['nis' => '250001', 'nama_lengkap' => 'Ahmad Fauzi']);
    $siti = Siswa::factory()->create(['nis' => '250002', 'nama_lengkap' => 'Siti Rahma', 'kelas_id' => null]);

    Livewire::test(CreateRapor::class)
        ->assertFormFieldExists('siswa_id', fn (Select $field): bool => $field->getOptions() === [
            $ahmad->id => '250001 - Ahmad Fauzi (VIII-B)',
            $siti->id => '250002 - Siti Rahma',
        ]);
});

it('defaults to the semester the current month usually belongs to', function (string $tanggal, Semester $semester) {
    $this->travelTo($tanggal);

    Livewire::test(CreateRapor::class)->assertSchemaStateSet(['semester' => $semester]);
})->with([
    'awal tahun ajaran' => ['2026-07-01', Semester::Ganjil],
    'akhir semester ganjil' => ['2026-12-31', Semester::Ganjil],
    'awal semester genap' => ['2027-01-02', Semester::Genap],
    'akhir tahun ajaran' => ['2027-06-30', Semester::Genap],
]);

it('saves no achievement when the placeholder row is left blank', function () {
    $rapor = Rapor::factory()->make();

    Livewire::test(CreateRapor::class)
        ->assertCount('data.prestasi', 1)
        ->fillForm([
            'siswa_id' => $rapor->siswa_id,
            'tahun_ajaran_id' => $rapor->tahun_ajaran_id,
            'semester' => Semester::Ganjil->value,
            'kelas_id' => $rapor->kelas_id,
            'nilaiRapor' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Rapor::query()->sole()->prestasi)->toBe([]);
});

it('requires the achievement name once its rank or note is filled in', function () {
    Livewire::test(CreateRapor::class)
        ->fillForm(['prestasi' => [['prestasi' => null, 'nilai' => 'Juara 1', 'keterangan' => null]]])
        ->call('create')
        ->assertHasFormErrors(['prestasi.0.prestasi' => 'required_with']);
});

it('shows one blank achievement row when editing a report card without achievements', function () {
    $rapor = Rapor::factory()->create(['prestasi' => null]);

    Livewire::test(EditRapor::class, ['record' => $rapor->getKey()])
        ->assertCount('data.prestasi', 1);
});

it('opens the promotion decision only for the even semester', function () {
    Livewire::test(CreateRapor::class)
        ->fillForm(['semester' => Semester::Ganjil->value])
        ->assertFormFieldVisible('keputusan')
        ->assertFormFieldDisabled('keputusan')
        ->fillForm(['semester' => Semester::Genap->value])
        ->assertFormFieldEnabled('keputusan')
        ->assertFormFieldEnabled('tanggal_keputusan');
});

it('stores the grades of a semester with computed letter grades', function () {
    $siswa = Siswa::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create();
    $kelas = Kelas::factory()->create();
    $matematika = MataPelajaran::factory()->create(['kkm' => 70]);

    Livewire::test(CreateRapor::class)
        ->fillForm([
            'siswa_id' => $siswa->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => Semester::Ganjil->value,
            'kelas_id' => $kelas->id,
            'nilaiRapor' => [
                ['mata_pelajaran_id' => $matematika->id, 'kkm' => 70, 'nilai_pengetahuan' => 95, 'nilai_keterampilan' => 65, 'sikap' => 'SB'],
            ],
            'nilaiEkstrakurikuler' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rapor = Rapor::query()->whereBelongsTo($siswa)->sole();

    expect($rapor->semester)->toBe(Semester::Ganjil)
        ->and($rapor->nilaiRapor->sole())
        ->predikat_pengetahuan->toBe('A')
        ->predikat_keterampilan->toBe('D')
        ->sikap->toBe('SB');
});

it('rejects a second report card for the same student, year and semester', function () {
    $rapor = Rapor::factory()->create(['semester' => Semester::Genap]);

    Livewire::test(CreateRapor::class)
        ->fillForm([
            'siswa_id' => $rapor->siswa_id,
            'tahun_ajaran_id' => $rapor->tahun_ajaran_id,
            'semester' => Semester::Genap->value,
            'kelas_id' => $rapor->kelas_id,
            'nilaiRapor' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['semester' => 'unique']);

    expect(Rapor::query()->count())->toBe(1);
});
