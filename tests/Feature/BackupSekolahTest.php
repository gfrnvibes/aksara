<?php

use App\Jobs\BuatBackup;
use App\Models\RiwayatBackup;
use App\Services\BackupSekolah;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use Spatie\Backup\Config\Config;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('r2');
    $this->directory = storage_path('framework/testing/backup-'.Str::uuid());
    mkdir($this->directory, 0777, true);
    $database = $this->directory.'/source.sqlite';
    touch($database);
    config([
        'database.connections.backup_source' => ['driver' => 'sqlite', 'database' => $database],
        'backup.backup.source.databases' => ['backup_source'],
        'backup.backup.source.files.include' => [$this->directory.'/photo.txt'],
        'backup.backup.destination.disks' => ['local', 'r2'],
        'backup.backup.name' => 'backup-test',
        'backup.backup.temporary_directory' => $this->directory.'/temp',
        'backup.backup.password' => null,
    ]);
    file_put_contents($this->directory.'/photo.txt', 'foto-contoh');
    DB::connection('backup_source')->statement('CREATE TABLE contoh (nama TEXT)');
    DB::connection('backup_source')->table('contoh')->insert(['nama' => 'data-penting']);
    Config::rebind();
});

afterEach(function () {
    DB::purge('backup_source');
    app('files')->deleteDirectory($this->directory);
});

it('writes a verified database and files archive to local and remote storage', function () {
    $backup = app(BackupSekolah::class);
    $batch = $backup->mulai(BackupType::DATABASE_AND_FILES, 'manual');
    (new BuatBackup($batch))->handle($backup);

    $results = RiwayatBackup::query()->where('batch', $batch)->get();
    expect($results)->toHaveCount(2);
    foreach ($results as $result) {
        expect($result->status)->toBe('berhasil')->and($result->ukuran)->toBeGreaterThan(0);
        Storage::disk($result->disk)->assertExists($result->path);
        $zip = new ZipArchive;
        expect($zip->open(Storage::disk($result->disk)->path($result->path)))->toBeTrue();
        $contents = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $contents .= $zip->getFromIndex($index);
        }
        expect($contents)->toContain('data-penting', 'foto-contoh');
        $zip->close();
    }
});

it('continues to local storage when another adapter throws a PHP error', function () {
    config(['backup.backup.destination.disks' => ['broken', 'local']]);
    Storage::extend('broken', fn () => throw new Error('Adapter tidak tersedia'));
    config(['filesystems.disks.broken' => ['driver' => 'broken']]);
    $backup = app(BackupSekolah::class);
    $batch = $backup->mulai(BackupType::ONLY_DATABASE, 'terjadwal');
    $backup->jalankan($batch);

    expect(RiwayatBackup::query()->where('disk', 'broken')->sole()->kesalahan)->toContain('Adapter tidak tersedia')
        ->and(RiwayatBackup::query()->where('disk', 'broken')->sole()->status)->toBe('gagal')
        ->and(RiwayatBackup::query()->where('disk', 'local')->sole()->status)->toBe('berhasil');
});

it('records dump failures for every destination', function () {
    config(['database.connections.backup_source.dump.dump_binary_path' => $this->directory.'/missing']);
    $backup = app(BackupSekolah::class);
    $batch = $backup->mulai(BackupType::ONLY_DATABASE, 'terjadwal');
    $backup->jalankan($batch);

    expect(RiwayatBackup::query()->where('status', 'gagal')->count())->toBe(2)
        ->and(RiwayatBackup::query()->whereNull('kesalahan')->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('backup-test');
});

it('does not let overlapping jobs use the same temporary directory', function () {
    $lock = Cache::lock('aksara:backup', 3600);
    $lock->get();
    try {
        $backup = app(BackupSekolah::class);
        $backup->jalankan($backup->mulai(BackupType::ONLY_DATABASE, 'manual'));
        expect(RiwayatBackup::query()->where('status', 'gagal')->count())->toBe(2);
    } finally {
        $lock->release();
    }
});

it('records unfinished attempts when the queue job fails', function () {
    $backup = app(BackupSekolah::class);
    $batch = $backup->mulai(BackupType::ONLY_DATABASE, 'manual');
    (new BuatBackup($batch))->failed(new RuntimeException('Worker timeout'));
    expect(RiwayatBackup::query()->where('status', 'gagal')->count())->toBe(2);
});

it('hides storage credentials in the displayed failure', function () {
    config(['filesystems.disks.r2.secret' => 'secret-test-123']);
    expect(app(BackupSekolah::class)->pesanKesalahan('Failed with secret-test-123'))
        ->toBe('Failed with [disembunyikan]');
});

it('runs scheduled backups through the command that records their results', function () {
    $this->artisan('aksara:backup', ['--only-db' => true])->assertSuccessful();
    expect(RiwayatBackup::query()->where('sumber', 'terjadwal')->where('status', 'berhasil')->count())->toBe(2);
});
