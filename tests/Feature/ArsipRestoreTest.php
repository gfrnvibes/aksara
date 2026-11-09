<?php

use App\Services\ArsipRestore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->root = storage_path('framework/testing/archive-'.Str::uuid());
    File::ensureDirectoryExists($this->root);
    config(['backup.backup.password' => null]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('normalizes Windows archive paths without extracting outside the destination', function () {
    $zip = new ZipArchive;
    $zip->open($this->root.'/archive.zip', ZipArchive::CREATE);
    $zip->addFromString('db-dumps\sqlite-fixture.sql', 'CREATE TABLE example (id INTEGER);');
    $zip->addFromString('storage\app\public\siswa\photo.jpg', 'photo');
    $zip->close();

    $result = app(ArsipRestore::class)->ekstrak($this->root.'/archive.zip', $this->root.'/result', 'sqlite');
    expect(file_get_contents($result['files'].'/siswa/photo.jpg'))->toBe('photo')
        ->and($result['has_files'])->toBeTrue();
});

it('rejects path traversal and absolute paths', function (string $path) {
    $zip = new ZipArchive;
    $zip->open($this->root.'/archive.zip', ZipArchive::CREATE);
    $zip->addFromString($path, 'unsafe');
    $zip->close();

    expect(fn () => app(ArsipRestore::class)->ekstrak($this->root.'/archive.zip', $this->root.'/result', 'sqlite'))
        ->toThrow(RuntimeException::class, 'path tidak aman');
})->with(['../escape', '/absolute', 'C:\outside', 'storage/app/public/../../escape']);

it('requires exactly one matching SQL database dump', function () {
    $zip = new ZipArchive;
    $zip->open($this->root.'/archive.zip', ZipArchive::CREATE);
    $zip->addFromString('db-dumps/sqlite-first.sql', 'SELECT 1;');
    $zip->addFromString('db-dumps/sqlite-second.sql', 'SELECT 1;');
    $zip->close();

    expect(fn () => app(ArsipRestore::class)->ekstrak($this->root.'/archive.zip', $this->root.'/result', 'sqlite'))
        ->toThrow(RuntimeException::class, 'satu dump database');
});

it('rejects symlinks from archive metadata', function () {
    $zip = new ZipArchive;
    $zip->open($this->root.'/archive.zip', ZipArchive::CREATE);
    $zip->addFromString('storage/app/public/link', '/outside');
    $zip->setExternalAttributesName('storage/app/public/link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();

    expect(fn () => app(ArsipRestore::class)->ekstrak($this->root.'/archive.zip', $this->root.'/result', 'sqlite'))
        ->toThrow(RuntimeException::class, 'Symlink');
});

it('validates encrypted archives using the configured backup password', function () {
    config(['backup.backup.password' => 'backup-pass']);
    $zip = new ZipArchive;
    $zip->open($this->root.'/archive.zip', ZipArchive::CREATE);
    $zip->setPassword('backup-pass');
    $zip->addFromString('db-dumps/sqlite-fixture.sql', 'CREATE TABLE example (id INTEGER);');
    $zip->setEncryptionName('db-dumps/sqlite-fixture.sql', ZipArchive::EM_AES_256);
    $zip->close();

    $result = app(ArsipRestore::class)->ekstrak($this->root.'/archive.zip', $this->root.'/result', 'sqlite');
    expect(file_get_contents($result['sql']))->toContain('CREATE TABLE');
});
