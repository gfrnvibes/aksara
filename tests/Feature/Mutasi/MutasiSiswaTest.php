<?php

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Filament\Resources\MutasiSiswas\Pages\ManageMutasiSiswas;
use App\Models\Kelas;
use App\Models\MutasiSiswa;
use App\Models\Siswa;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('marks a student who moves to another school and records it in the Buku Induk', function () {
    $siswa = Siswa::factory()->create();

    MutasiSiswa::factory()->for($siswa)->create([
        'jenis' => JenisMutasi::Pindah,
        'tanggal' => '2026-09-01',
        'sekolah' => 'MTs Negeri 1 Garut',
        'alasan' => 'Mengikuti orang tua',
    ]);

    expect($siswa->fresh())
        ->status->toBe(StatusSiswa::Pindah)
        ->tanggal_meninggalkan_sekolah->toDateString()->toBe('2026-09-01')
        ->alasan_meninggalkan_sekolah->toBe('Pindah ke MTs Negeri 1 Garut: Mengikuti orang tua');
});

it('makes the student active again when the departure is deleted', function () {
    $siswa = Siswa::factory()->create();
    $mutasi = MutasiSiswa::factory()->for($siswa)->create(['jenis' => JenisMutasi::Keluar]);

    expect($siswa->fresh()->status)->toBe(StatusSiswa::Keluar);

    $mutasi->delete();

    expect($siswa->fresh())
        ->status->toBe(StatusSiswa::Aktif)
        ->tanggal_meninggalkan_sekolah->toBeNull();
});

it('leaves a graduate alone when an older departure is deleted', function () {
    $siswa = Siswa::factory()->create();
    $mutasi = MutasiSiswa::factory()->for($siswa)->create(['jenis' => JenisMutasi::Pindah]);
    $siswa->update(['status' => StatusSiswa::Lulus]);

    $mutasi->delete();

    expect($siswa->fresh()->status)->toBe(StatusSiswa::Lulus);
});

it('records where a transfer student came from and the class they joined', function () {
    $kelas = Kelas::factory()->create(['tingkat' => 8]);
    $siswa = Siswa::factory()->create(['kelas_id' => null, 'pindahan_dari' => null]);

    MutasiSiswa::factory()->for($siswa)->for($kelas)->create([
        'jenis' => JenisMutasi::Masuk,
        'tanggal' => '2026-08-15',
        'sekolah' => 'SMP Negeri 2 Cisurupan',
        'alasan' => 'Pindah domisili',
    ]);

    expect($siswa->fresh())
        ->status->toBe(StatusSiswa::Aktif)
        ->kelas_id->toBe($kelas->id)
        ->pindahan_dari->toBe('SMP Negeri 2 Cisurupan')
        ->alasan_pindah->toBe('Pindah domisili')
        ->diterima_di_tingkat->toBe(8)
        ->tanggal_diterima->toDateString()->toBe('2026-08-15');
});

it('records a departure from the transfer page, offering only active students', function () {
    actingAs(userWithPermissions(['ViewAny:MutasiSiswa', 'Create:MutasiSiswa']));
    $aktif = Siswa::factory()->create(['nama_lengkap' => 'Ahmad Aktif']);
    Siswa::factory()->create(['nama_lengkap' => 'Budi Lulus', 'status' => StatusSiswa::Lulus]);

    Livewire::test(ManageMutasiSiswas::class)
        ->mountAction('create')
        ->assertSchemaComponentExists('siswa_id', checkComponentUsing: fn ($field): bool => array_values($field->getSearchResults('')) === ["{$aktif->nis} - Ahmad Aktif"])
        ->fillForm(['jenis' => JenisMutasi::Pindah->value, 'siswa_id' => $aktif->id, 'sekolah' => 'MTs Al-Falah'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($aktif->fresh()->status)->toBe(StatusSiswa::Pindah);
});

it('keeps the transfer page away from users without permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(ManageMutasiSiswas::class)->assertForbidden();
});
