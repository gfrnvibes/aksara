<?php

use App\Filament\Pages\BackupData;
use App\Jobs\PulihkanBackup;
use App\Services\RestoreBackup;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->root = storage_path('framework/testing/restore-action-'.Str::uuid());
    config([
        'backup.backup.name' => 'restore-action', 'backup.backup.destination.disks' => ['local'],
        'backup.monitor_backups.0.name' => 'restore-action', 'backup.monitor_backups.0.disks' => ['local'],
    ]);
    Storage::set('local', Storage::build(['driver' => 'local', 'root' => $this->root]));
    $this->path = 'restore-action/2026-11-09-01-30-00.zip';
    $this->key = sha1("local\0{$this->path}");
    Storage::disk('local')->put($this->path, 'archive');
    FilamentSpatieLaravelBackup::clearBackupDestinationCaches();
    $this->user = userWithPermissions([BackupData::IZIN]);
    $this->actingAs($this->user);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('does not offer restore to backup viewers without the super admin role', function () {
    Livewire::test(BackupData::class)->assertActionHidden(TestAction::make('restore')->table($this->key));
});

it('requires the current password and the RESTORE confirmation', function () {
    Bus::fake();
    $this->user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));

    Livewire::test(BackupData::class)
        ->callAction(TestAction::make('restore')->table($this->key), ['konfirmasi' => 'RESTORE', 'password' => 'wrong-password'])
        ->assertHasActionErrors(['password' => 'current_password']);
    Livewire::test(BackupData::class)
        ->callAction(TestAction::make('restore')->table($this->key), ['konfirmasi' => 'wrong', 'password' => 'password'])
        ->assertHasActionErrors(['konfirmasi' => 'in']);
    Bus::assertNotDispatched(PulihkanBackup::class);
});

it('dispatches the confirmed restore after the response without putting it into the database queue', function () {
    Bus::fake();
    $this->user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));
    $id = (string) Str::uuid();
    $service = Mockery::mock(RestoreBackup::class);
    $service->shouldReceive('mulai')->once()->withArgs(fn ($user, $disk, $path, $files): bool => $user->is($this->user) && $disk === 'local' && $path === $this->path && $files === false)->andReturn($id);
    app()->instance(RestoreBackup::class, $service);

    Livewire::test(BackupData::class)
        ->callAction(TestAction::make('restore')->table($this->key), ['konfirmasi' => 'RESTORE', 'password' => 'password', 'files' => false])
        ->assertHasNoActionErrors()->assertNotified('Restore dijadwalkan');
    Bus::assertDispatched(PulihkanBackup::class, fn (PulihkanBackup $job): bool => $job->id === $id && $job->connection === 'deferred');
});
