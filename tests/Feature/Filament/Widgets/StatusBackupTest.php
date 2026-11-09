<?php

use App\Filament\Pages\BackupData;
use App\Filament\Widgets\StatusBackup;
use App\Models\RiwayatBackup;
use App\Support\PemantauanBackup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;

beforeEach(function () {
    config([
        'backup.backup.name' => 'monitor-test',
        'backup.backup.destination.disks' => ['local', 'r2'],
        // Reproduce the old monitoring configuration shown in the screenshot.
        'backup.monitor_backups.0.name' => 'monitor-test',
        'backup.monitor_backups.0.disks' => ['local', 'google'],
    ]);
    Storage::fake('local');
    Storage::fake('r2');
    Storage::disk('r2')->put('monitor-test/2026-11-09-01-30-00.zip', 'backup');
    PemantauanBackup::clearBackupDestinationCaches();
    $this->actingAs(userWithPermissions([BackupData::IZIN]));
});

it('shows an R2 monitoring card even when the old monitor list omits R2', function () {
    Livewire::test(StatusBackup::class)
        ->assertSee('Cloudflare R2')
        ->assertSee('Server (lokal)')
        ->assertDontSee('Google Drive');
});

it('keeps the R2 card visible when its backup history exists', function () {
    config(['backup.backup.destination.disks' => ['local']]);
    RiwayatBackup::query()->create([
        'batch' => (string) Str::uuid(), 'disk' => 'r2',
        'jenis' => BackupType::ONLY_DATABASE->value, 'sumber' => 'manual',
        'status' => 'berhasil',
    ]);

    Livewire::test(StatusBackup::class)->assertSee('Cloudflare R2')->assertSee('1 backup');
});
