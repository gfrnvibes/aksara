<?php

use App\Filament\Pages\BackupData;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // A fresh folder per test: reusing one races Windows' delayed directory deletion.
    $this->folder = 'backup-test-'.Str::lower(Str::random(10));
    $this->file = "{$this->folder}/2026-10-01-01-30-00.zip";

    config([
        'backup.backup.name' => $this->folder,
        'backup.backup.destination.disks' => ['local'],
        'backup.monitor_backups.0.name' => $this->folder,
        'backup.monitor_backups.0.disks' => ['local'],
    ]);

    Storage::persistentFake('local');
    Storage::disk('local')->put($this->file, 'isi-zip');
    FilamentSpatieLaravelBackup::clearBackupDestinationCaches();

    $this->admin = userWithPermissions([BackupData::IZIN]);
});

afterEach(function () {
    rescue(fn () => Storage::disk('local')->deleteDirectory($this->folder), report: false);
    rescue(fn () => Storage::disk('local')->delete("{$this->folder}-rahasia.zip"), report: false);
});

/**
 * A short-lived signed download link, as the backup page generates it.
 */
function linkUnduhBackup(string $disk, string $path): string
{
    return URL::temporarySignedRoute('backup.unduh', now()->addMinutes(5), ['disk' => $disk, 'path' => $path]);
}

it('lists the backups and their health for users with the backup permission', function () {
    actingAs($this->admin);

    Livewire::test(BackupData::class)
        ->assertOk()
        ->assertSee('2026-10-01-01-30-00.zip');
});

it('denies the backup page to users without the backup permission', function () {
    $operator = User::factory()->create()->assignRole(Role::findOrCreate('operator', 'web'));

    actingAs($operator)
        ->get(BackupData::getUrl())
        ->assertForbidden();
});

it('queues a backup of the chosen type and records who started it', function () {
    Bus::fake();
    actingAs($this->admin);

    Livewire::test(BackupData::class)
        ->callAction('buatBackup', ['jenis' => BackupType::ONLY_DATABASE->value])
        ->assertNotified('Backup sedang diproses');

    Bus::assertDispatched(CreateBackupJob::class, fn (CreateBackupJob $job): bool => (fn () => $this->type)->call($job) === BackupType::ONLY_DATABASE);

    expect(Activity::query()->where('event', 'backup_dibuat')->sole()->causer->is($this->admin))->toBeTrue();
});

it('deletes a backup file from its disk', function () {
    actingAs($this->admin);

    Livewire::test(BackupData::class)
        ->callAction(TestAction::make('hapus')->table(sha1("local\0{$this->file}")));

    Storage::disk('local')->assertMissing($this->file);
    expect(Activity::query()->where('event', 'backup_dihapus')->exists())->toBeTrue();
});

describe('download', function () {
    it('streams the backup through a signed link and logs the download', function () {
        actingAs($this->admin)
            ->get(linkUnduhBackup('local', $this->file))
            ->assertOk()
            ->assertDownload('2026-10-01-01-30-00.zip');

        expect(Activity::query()->where('event', 'backup_diunduh')->sole()->causer->is($this->admin))->toBeTrue();
    });

    it('rejects a link without a valid signature', function () {
        actingAs($this->admin)
            ->get(route('backup.unduh', ['disk' => 'local', 'path' => $this->file]))
            ->assertForbidden();
    });

    it('rejects a signed link for a user without the backup permission', function () {
        actingAs(User::factory()->create())
            ->get(linkUnduhBackup('local', $this->file))
            ->assertForbidden();
    });

    it('refuses files outside the backup folder', function (string $disk, string $path) {
        Storage::disk('local')->put("{$this->folder}-rahasia.zip", 'x');

        actingAs($this->admin)
            ->get(linkUnduhBackup($disk, str_replace('{folder}', $this->folder, $path)))
            ->assertNotFound();
    })->with([
        'outside folder' => ['local', '{folder}-rahasia.zip'],
        'path traversal' => ['local', '{folder}/../{folder}-rahasia.zip'],
        'not a zip' => ['local', '{folder}/.env'],
        'unknown disk' => ['public', '{folder}/2026-10-01-01-30-00.zip'],
    ]);
});
