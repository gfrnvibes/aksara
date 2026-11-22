<?php

use App\Filament\Bendahara\Pages\TabunganSiswa as TabunganSiswaPage;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TabunganSiswa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders buku tabungan page in bendahara panel and records deposit and withdrawal', function () {
    Filament::setCurrentPanel('bendahara');

    $user = User::factory()->create();
    $role = Role::findOrCreate('bendahara', 'web');
    $user->assignRole($role);

    $kelas = Kelas::factory()->create(['nama' => 'VII A', 'tingkat' => 7]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Tabung']);

    actingAs($user);

    Livewire::test(TabunganSiswaPage::class)
        ->assertSuccessful()
        ->callAction('catatTransaksi', [
            'siswa_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jenis' => 'setor',
            'nominal' => 50000,
            'keterangan' => 'Setoran awal',
        ])
        ->assertHasNoActionErrors()
        ->callAction('catatTransaksi', [
            'siswa_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jenis' => 'tarik',
            'nominal' => 15000,
            'keterangan' => 'Tarik jajan',
        ])
        ->assertHasNoActionErrors();

    expect(TabunganSiswa::saldoUntuk($siswa->id))->toBe(35000);
});
