<?php

use App\Filament\Widgets\AksesCepatAdmin;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Pages\Dashboard;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders compact panel switcher and quick access cards on admin dashboard for super admin', function () {
    $superAdmin = User::factory()->create()->assignRole(config('filament-shield.super_admin.name'));
    actingAs($superAdmin);

    get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Pindah Panel')
        ->assertSee('Panel Ujian')
        ->assertSee('Panel Bendahara')
        ->assertSee('Panel Akreditasi')
        ->assertSee('GTK &amp; Pegawai', false)
        ->assertSee('SPMB / PPDB')
        ->assertSee('Persuratan')
        ->assertSee('Perpustakaan')
        ->assertSee('Backup Data');
});

it('only shows authorized panels and quick access cards on AksesCepatAdmin widget', function () {
    $operator = User::factory()->create()->assignRole('operator');
    actingAs($operator);

    Livewire::test(AksesCepatAdmin::class)
        ->assertSuccessful()
        ->assertSee('GTK &amp; Pegawai', false)
        ->assertDontSee('Panel Bendahara')
        ->assertDontSee('Backup Data');
});
