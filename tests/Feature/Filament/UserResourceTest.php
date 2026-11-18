<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Gtk;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    actingAs(User::factory()->create()->assignRole(config('filament-shield.super_admin.name')));
});

it('prefills user name and email from selected GTK and links the GTK record', function () {
    $gtk = Gtk::factory()->create([
        'nama' => 'Ahmad Fauzi, S.Pd',
        'email' => 'ahmad.fauzi@madrasah.test',
        'user_id' => null,
    ]);

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'gtk_id' => $gtk->id,
            'name' => $gtk->nama,
            'email' => $gtk->email,
            'password' => 'rahasia123',
        ])
        ->assertHasNoActionErrors();

    $user = User::query()->where('email', 'ahmad.fauzi@madrasah.test')->firstOrFail();

    expect($user->name)->toBe('Ahmad Fauzi, S.Pd')
        ->and($gtk->refresh()->user_id)->toBe($user->id);
});
