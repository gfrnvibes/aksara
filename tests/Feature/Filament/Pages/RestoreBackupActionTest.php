<?php

use App\Filament\Pages\BackupData;
use App\Jobs\PulihkanBackup;
use App\Services\CatatanRestore;
use App\Services\RestoreBackup;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
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

it('does not offer upload restore to backup viewers without the super admin role', function () {
    Livewire::test(BackupData::class)->assertActionHidden('restoreUnggah');
});

it('validates uploaded zip, RESTORE confirmation, and password before dispatching upload restore', function () {
    Bus::fake();
    $this->user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));

    Livewire::test(BackupData::class)
        ->callAction('restoreUnggah', [
            'arsip' => UploadedFile::fake()->create('bukan-zip.sql', 10, 'text/plain'),
            'konfirmasi' => 'RESTORE',
            'password' => 'password',
        ])
        ->assertHasActionErrors(['arsip']);

    Livewire::test(BackupData::class)
        ->callAction('restoreUnggah', [
            'arsip' => UploadedFile::fake()->create('backup.zip', 100, 'application/zip'),
            'konfirmasi' => 'SALAH',
            'password' => 'wrong-password',
        ])
        ->assertHasActionErrors(['konfirmasi' => 'in', 'password' => 'current_password']);

    Bus::assertNotDispatched(PulihkanBackup::class);
});

it('dispatches upload restore on deferred queue and cleans up the uploaded file if starting fails', function () {
    Bus::fake();
    $this->user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));
    $id = (string) Str::uuid();
    $service = Mockery::mock(RestoreBackup::class);
    $service->shouldReceive('mulai')->once()->withArgs(
        fn ($user, $disk, $path, $files, $archivePassword = null): bool => $user->is($this->user)
            && $disk === RestoreBackup::DISK_UNGGAH
            && str_starts_with($path, RestoreBackup::DIREKTORI_UNGGAH.'/')
            && str_ends_with($path, '.zip')
            && $files === true
            && $archivePassword === 'arsip-pass'
    )->andReturn($id);
    app()->instance(RestoreBackup::class, $service);

    Livewire::test(BackupData::class)
        ->callAction('restoreUnggah', [
            'arsip' => UploadedFile::fake()->create('backup.zip', 100, 'application/zip'),
            'files' => true,
            'password_arsip' => 'arsip-pass',
            'konfirmasi' => 'RESTORE',
            'password' => 'password',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Restore dijadwalkan');

    Bus::assertDispatched(PulihkanBackup::class, fn (PulihkanBackup $job): bool => $job->id === $id && $job->connection === 'deferred');

    $failingService = Mockery::mock(RestoreBackup::class);
    $failingService->shouldReceive('mulai')->once()->andThrow(new RuntimeException('Gagal memulai'));
    app()->instance(RestoreBackup::class, $failingService);

    Livewire::test(BackupData::class)
        ->callAction('restoreUnggah', [
            'arsip' => UploadedFile::fake()->create('backup-gagal.zip', 100, 'application/zip'),
            'files' => false,
            'konfirmasi' => 'RESTORE',
            'password' => 'password',
        ])
        ->assertNotified('Restore gagal dimulai');

    expect(Storage::disk('local')->files(RestoreBackup::DIREKTORI_UNGGAH))->toHaveCount(1);
});

it('displays a live restore progress banner for super admin when a restore is active or recently finished', function () {
    $this->user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));
    $journalDir = storage_path('framework/testing/restore-banner-'.Str::uuid());
    $journal = new class($journalDir) extends CatatanRestore
    {
        public function __construct(public string $dir) {}

        protected function directory(): string
        {
            return $this->dir;
        }
    };
    app()->instance(CatatanRestore::class, $journal);

    try {
        $id = $journal->buat([
            'actor_id' => $this->user->id,
            'actor' => $this->user->name,
            'disk' => RestoreBackup::DISK_UNGGAH,
            'path' => RestoreBackup::DIREKTORI_UNGGAH.'/sample.zip',
            'files' => false,
        ]);
        $journal->ubah($id, ['status' => 'diproses']);

        Livewire::test(BackupData::class)
            ->assertSee('Memulihkan database dan berkas...')
            ->assertSee('80%');

        $journal->ubah($id, [
            'status' => 'gagal',
            'error' => 'Backup atau restore lain masih berjalan.',
            'finished_at' => now()->toDateTimeString(),
        ]);

        Livewire::test(BackupData::class)
            ->assertSee('Restore gagal.')
            ->assertSee('100%')
            ->assertSee('Backup atau restore lain masih berjalan.');
    } finally {
        File::deleteDirectory($journalDir);
    }
});
