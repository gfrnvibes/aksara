<?php

use App\Enums\JenisKelamin;
use App\Enums\JenisOrangTua;
use App\Filament\Resources\Siswas\Pages\CreateSiswa;
use App\Filament\Resources\Siswas\Pages\EditSiswa;
use App\Models\OrangTua;
use App\Models\Siswa;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions(['ViewAny:Siswa', 'View:Siswa', 'Create:Siswa', 'Update:Siswa']));
});

it('creates a student together with the father and mother records', function () {
    Livewire::test(CreateSiswa::class)
        ->fillForm([
            'nis' => '250001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'jenis_kelamin' => JenisKelamin::LakiLaki,
            'ayah' => ['nama' => 'Budi Santoso', 'pekerjaan' => 'Petani'],
            'ibu' => ['nama' => 'Siti Aminah'],
            'wali' => ['nama' => null],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $siswa = Siswa::query()->where('nis', '250001')->sole();

    expect($siswa->ayah)
        ->jenis->toBe(JenisOrangTua::Ayah)
        ->nama->toBe('Budi Santoso')
        ->pekerjaan->toBe('Petani')
        ->and($siswa->ibu)
        ->jenis->toBe(JenisOrangTua::Ibu)
        ->nama->toBe('Siti Aminah')
        ->and($siswa->wali)->toBeNull();
});

it('rejects a duplicate NIS', function () {
    Siswa::factory()->create(['nis' => '250001']);

    Livewire::test(CreateSiswa::class)
        ->fillForm([
            'nis' => '250001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'jenis_kelamin' => JenisKelamin::LakiLaki,
        ])
        ->call('create')
        ->assertHasFormErrors(['nis' => 'unique']);
});

it('removes the guardian when the guardian name is cleared', function () {
    $siswa = Siswa::factory()->create();
    OrangTua::factory()->for($siswa)->create(['jenis' => JenisOrangTua::Wali, 'nama' => 'Paman Ali']);

    Livewire::test(EditSiswa::class, ['record' => $siswa->getKey()])
        ->fillForm(['wali' => ['nama' => null]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($siswa->wali()->exists())->toBeFalse();
});
