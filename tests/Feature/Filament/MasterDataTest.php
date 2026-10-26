<?php

use App\Filament\Resources\Kelas\Pages\ManageKelas;
use App\Filament\Resources\MataPelajarans\Pages\ManageMataPelajarans;
use App\Filament\Resources\Siswas\Pages\CreateSiswa;
use App\Filament\Resources\Siswas\Pages\EditSiswa;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions([
        'ViewAny:Kelas', 'Update:Kelas', 'Delete:Kelas', 'DeleteAny:Kelas',
        'ViewAny:MataPelajaran', 'Update:MataPelajaran', 'Delete:MataPelajaran', 'DeleteAny:MataPelajaran',
        'ViewAny:Siswa', 'View:Siswa', 'Create:Siswa', 'Update:Siswa',
    ]));
});

it('explains why a class used by a report card cannot be deleted', function () {
    $kelas = Kelas::factory()->create(['nama' => 'VII-A']);
    Rapor::factory()->for($kelas)->create();

    Livewire::test(ManageKelas::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($kelas))
        ->assertNotified('Data tidak dapat dihapus');

    expect($kelas->fresh())->not->toBeNull();
});

it('keeps a class used by a report card even after its report card is moved to the trash', function () {
    $kelas = Kelas::factory()->create();
    Rapor::factory()->for($kelas)->create()->delete();

    expect($kelas->delete())->toBeFalse()
        ->and($kelas->fresh())->not->toBeNull();
});

it('deletes the unused subjects of a bulk selection and keeps the graded ones', function () {
    $dinilai = MataPelajaran::factory()->create();
    NilaiRapor::factory()->for($dinilai)->create();
    $belumDipakai = MataPelajaran::factory()->create();

    Livewire::test(ManageMataPelajarans::class)
        ->selectTableRecords([$dinilai->getKey(), $belumDipakai->getKey()])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect($dinilai->fresh())->not->toBeNull()
        ->and($belumDipakai->fresh())->toBeNull();
});

it('offers only active classes for a new student', function () {
    $aktif = Kelas::factory()->create();
    $nonaktif = Kelas::factory()->create(['is_aktif' => false]);

    Livewire::test(CreateSiswa::class)
        ->assertFormFieldExists('kelas_id', fn (Select $field): bool => array_key_exists($aktif->getKey(), $field->getOptions())
            && ! array_key_exists($nonaktif->getKey(), $field->getOptions()));
});

it('keeps an inactive class selectable for the student already in it', function () {
    $nonaktif = Kelas::factory()->create(['is_aktif' => false]);
    $siswa = Siswa::factory()->for($nonaktif)->create();

    Livewire::test(EditSiswa::class, ['record' => $siswa->getKey()])
        ->assertFormFieldExists('kelas_id', fn (Select $field): bool => array_key_exists($nonaktif->getKey(), $field->getOptions()));
});
