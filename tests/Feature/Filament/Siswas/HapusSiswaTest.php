<?php

use App\Filament\Resources\Rapors\Pages\ListRapors;
use App\Filament\Resources\Siswas\Pages\CreateSiswa;
use App\Filament\Resources\Siswas\Pages\EditSiswa;
use App\Filament\Resources\Siswas\Pages\ListSiswas;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

const IZIN_KELOLA_SISWA = [
    'ViewAny:Siswa', 'View:Siswa', 'Update:Siswa', 'Delete:Siswa', 'DeleteAny:Siswa', 'Restore:Siswa', 'RestoreAny:Siswa',
    'ViewAny:Rapor', 'View:Rapor',
];

beforeEach(function () {
    $this->siswa = Siswa::factory()->create();
    $this->rapor = Rapor::factory()->for($this->siswa)->create();
    NilaiRapor::factory()->for($this->rapor)->create();
});

it('moves a deleted student to the trash and keeps every grade', function () {
    actingAs(userWithPermissions(IZIN_KELOLA_SISWA));

    Livewire::test(EditSiswa::class, ['record' => $this->siswa->getKey()])
        ->callAction(DeleteAction::class);

    expect($this->siswa->fresh()->trashed())->toBeTrue()
        ->and($this->rapor->fresh())->not->toBeNull()
        ->and($this->rapor->nilaiRapor()->count())->toBe(1);
});

it('hides the report cards of a student in the trash until the student is restored', function () {
    actingAs(userWithPermissions(IZIN_KELOLA_SISWA));
    $this->siswa->delete();

    Livewire::test(ListRapors::class)->assertCanNotSeeTableRecords([$this->rapor]);

    Livewire::test(ListSiswas::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make(RestoreAction::class)->table($this->siswa));

    expect($this->siswa->fresh()->trashed())->toBeFalse();
    Livewire::test(ListRapors::class)->assertCanSeeTableRecords([$this->rapor]);
});

it('opens a student in the trash on the edit page', function () {
    actingAs(userWithPermissions(IZIN_KELOLA_SISWA));
    $this->siswa->delete();

    Livewire::test(EditSiswa::class, ['record' => $this->siswa->getKey()])
        ->assertOk()
        ->assertActionVisible(RestoreAction::class);
});

it('lets only users with the force-delete permission empty the trash', function () {
    $this->siswa->delete();

    actingAs(userWithPermissions(IZIN_KELOLA_SISWA));
    Livewire::test(EditSiswa::class, ['record' => $this->siswa->getKey()])
        ->assertActionHidden(ForceDeleteAction::class);

    actingAs(userWithPermissions([...IZIN_KELOLA_SISWA, 'ForceDelete:Siswa']));
    Livewire::test(EditSiswa::class, ['record' => $this->siswa->getKey()])
        ->callAction(ForceDeleteAction::class);

    expect(Siswa::withTrashed()->find($this->siswa->getKey()))->toBeNull()
        ->and(Rapor::withTrashed()->find($this->rapor->getKey()))->toBeNull();
});

it('rejects a new student whose NIS belongs to a student in the trash', function () {
    actingAs(userWithPermissions([...IZIN_KELOLA_SISWA, 'Create:Siswa']));
    $this->siswa->delete();

    Livewire::test(CreateSiswa::class)
        ->fillForm([
            'nis' => $this->siswa->nis,
            'nama_lengkap' => 'Siswa Baru',
            'jenis_kelamin' => 'L',
        ])
        ->call('create')
        ->assertHasFormErrors(['nis' => 'unique']);
});
