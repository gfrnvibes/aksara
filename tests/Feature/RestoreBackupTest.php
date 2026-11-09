<?php

use App\Filament\Pages\BackupData;
use App\Filament\Widgets\RiwayatRestore;
use App\Models\User;
use App\Services\ArsipRestore;
use App\Services\CatatanRestore;
use App\Services\PemulihanDatabase;
use App\Services\RestoreBackup;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->root = storage_path('framework/testing/restore-'.Str::uuid());
    File::ensureDirectoryExists($this->root.'/public');
    file_put_contents($this->root.'/public/photo.txt', 'current-photo');
    touch($this->root.'/database.sqlite');
    config([
        'database.connections.restore_fixture' => ['driver' => 'sqlite', 'database' => $this->root.'/database.sqlite'],
        'backup.backup.name' => 'restore-test', 'backup.backup.destination.disks' => ['local', 'r2'],
        'backup.backup.password' => null,
    ]);
    DB::connection('restore_fixture')->statement('CREATE TABLE entries (value TEXT)');
    DB::connection('restore_fixture')->table('entries')->insert(['value' => 'current']);
    Storage::set('local', Storage::build(['driver' => 'local', 'root' => $this->root.'/local', 'throw' => true]));
    Storage::set('r2', Storage::build(['driver' => 'local', 'root' => $this->root.'/r2', 'throw' => true]));
    $this->admin = userWithPermissions([BackupData::IZIN])->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));
    $this->journal = new class($this->root) extends CatatanRestore
    {
        public function __construct(public string $root) {}

        protected function directory(): string
        {
            return $this->root.'/history';
        }
    };
    $this->restore = new class($this->journal, new ArsipRestore, new PemulihanDatabase, $this->root) extends RestoreBackup
    {
        public bool $maintenance = false;

        public bool $failMigration = false;

        public bool $failSafety = false;

        public function useDatabase(PemulihanDatabase $database): void
        {
            $this->database = $database;
        }

        public function __construct(CatatanRestore $journal, ArsipRestore $archive, PemulihanDatabase $database, public string $root)
        {
            parent::__construct($journal, $archive, $database);
        }

        protected function connection(): Connection
        {
            return DB::connection('restore_fixture');
        }

        protected function temporaryRoot(): string
        {
            return $this->root.'/work';
        }

        protected function marker(): string
        {
            return $this->root.'/marker';
        }

        protected function publicDirectory(): string
        {
            return $this->root.'/public';
        }

        protected function down(): void
        {
            $this->maintenance = true;
        }

        protected function up(): void
        {
            $this->maintenance = false;
        }

        protected function bersihkanStatusSementara(): void {}

        protected function migrasi(): void
        {
            if ($this->failMigration) {
                throw new RuntimeException('Migration gagal');
            }
        }

        protected function cadanganPengaman(string $id): string
        {
            if ($this->failSafety) {
                throw new RuntimeException('Backup pengaman gagal');
            }
            $sql = $this->root.'/safety.sql';
            DbDumperFactory::createFromConnection('restore_fixture')->dumpToFile($sql);
            $path = 'restore-test/safety-'.$id.'.zip';
            $file = Storage::disk('local')->path($path);
            File::ensureDirectoryExists(dirname($file));
            $zip = new ZipArchive;
            $zip->open($file, ZipArchive::CREATE);
            $zip->addFile($sql, 'db-dumps/sqlite-restore_fixture-database.sql');
            $zip->addFile($this->root.'/public/photo.txt', 'storage/app/public/photo.txt');
            $zip->close();

            return $path;
        }
    };
    $zip = new ZipArchive;
    $zip->open($this->root.'/source.zip', ZipArchive::CREATE);
    $zip->addFromString('db-dumps/sqlite-fixture.sql', "BEGIN TRANSACTION; CREATE TABLE entries (value TEXT); INSERT INTO entries VALUES ('backup'); COMMIT;");
    $zip->addFromString('storage/app/public/photo.txt', 'archived-photo');
    $zip->close();
    Storage::disk('r2')->put('restore-test/backup.zip', file_get_contents($this->root.'/source.zip'));
});

afterEach(function () {
    DB::purge('restore_fixture');
    File::deleteDirectory($this->root);
});

it('restores an isolated database and photos from remote storage after a safety backup', function () {
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/backup.zip', true);
    $this->restore->jalankan($id);

    $record = $this->journal->ambil($id);
    expect($record['status'])->toBe('berhasil')
        ->and(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('backup')
        ->and(file_get_contents($this->root.'/public/photo.txt'))->toBe('archived-photo')
        ->and($this->restore->maintenance)->toBeFalse();
    Storage::disk('local')->assertExists($record['safety_path']);
    expect($this->journal->semua())->toHaveCount(1);
});

it('can restore only the database while preserving current photos', function () {
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/backup.zip', false);
    $this->restore->jalankan($id);

    expect($this->journal->ambil($id)['status'])->toBe('berhasil')
        ->and(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('backup')
        ->and(file_get_contents($this->root.'/public/photo.txt'))->toBe('current-photo');
});

it('returns the database and photos to their previous state if post-restore migration fails', function () {
    $this->restore->failMigration = true;
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/backup.zip', true);
    $this->restore->jalankan($id);

    expect($this->journal->ambil($id)['status'])->toBe('gagal')
        ->and($this->journal->ambil($id)['error'])->toContain('Migration gagal')
        ->and(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('current')
        ->and(file_get_contents($this->root.'/public/photo.txt'))->toBe('current-photo')
        ->and($this->restore->maintenance)->toBeFalse();
});

it('leaves the database and files unchanged when the safety backup fails', function () {
    $this->restore->failSafety = true;
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/backup.zip', true);
    $this->restore->jalankan($id);

    expect($this->journal->ambil($id)['status'])->toBe('gagal')
        ->and(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('current')
        ->and(file_get_contents($this->root.'/public/photo.txt'))->toBe('current-photo');
});

it('records invalid archives as failures without touching data', function () {
    Storage::disk('r2')->put('restore-test/bad.zip', 'not-a-zip');
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/bad.zip', false);
    $this->restore->jalankan($id);

    expect($this->journal->ambil($id)['status'])->toBe('gagal')
        ->and(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('current')
        ->and($this->restore->maintenance)->toBeFalse();
});

it('recovers safely when the archive contains invalid SQL', function () {
    $zip = new ZipArchive;
    $zip->open($this->root.'/invalid-sql.zip', ZipArchive::CREATE);
    $zip->addFromString('db-dumps/sqlite-fixture.sql', 'THIS IS NOT VALID SQL;');
    $zip->addFromString('storage/app/public/photo.txt', 'archived-photo');
    $zip->close();
    Storage::disk('r2')->put('restore-test/invalid-sql.zip', file_get_contents($this->root.'/invalid-sql.zip'));
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/invalid-sql.zip', true);
    $this->restore->jalankan($id);

    expect($this->journal->ambil($id)['status'])->toBe('gagal')
        ->and(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('current')
        ->and(file_get_contents($this->root.'/public/photo.txt'))->toBe('current-photo')
        ->and($this->restore->maintenance)->toBeFalse();
});

it('does not execute the same restore twice', function () {
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/backup.zip', false);
    $this->restore->jalankan($id);
    DB::connection('restore_fixture')->table('entries')->update(['value' => 'after-restore']);
    $this->restore->jalankan($id);

    expect(DB::connection('restore_fixture')->table('entries')->value('value'))->toBe('after-restore');
});

it('rejects users without the super admin role', function () {
    $operator = User::factory()->create()->assignRole(Role::findOrCreate('operator', 'web'));
    expect(fn () => $this->restore->mulai($operator, 'r2', 'restore-test/backup.zip', false))->toThrow(RuntimeException::class, 'Hanya super admin');
    expect($this->journal->semua())->toBeEmpty();
});

it('rejects archive paths outside the configured backup folder', function () {
    expect(fn () => $this->restore->mulai($this->admin, 'r2', 'restore-test/../backup.zip', false))->toThrow(RuntimeException::class, 'lokasinya tidak diizinkan');
});

it('keeps maintenance and recovery files when automatic rollback cannot complete', function () {
    $database = new class extends PemulihanDatabase
    {
        public int $calls = 0;

        public function pulihkan(Connection $connection, string $sql): void
        {
            if (++$this->calls === 2) {
                throw new RuntimeException('Rollback tidak dapat diselesaikan');
            }
            parent::pulihkan($connection, $sql);
        }
    };
    $this->restore->useDatabase($database);
    $this->restore->failMigration = true;
    $id = $this->restore->mulai($this->admin, 'r2', 'restore-test/backup.zip', true);
    $this->restore->jalankan($id);

    expect($this->journal->ambil($id)['status'])->toBe('gagal')
        ->and($this->journal->ambil($id)['error'])->toContain('Gunakan backup pengaman')
        ->and($this->restore->maintenance)->toBeTrue()
        ->and(is_dir($this->root.'/work/'.$id.'/previous-public'))->toBeTrue()
        ->and(is_file($this->root.'/marker'))->toBeTrue();
});

it('renders restore history including the failure message without relying on database history', function () {
    $id = $this->journal->buat([
        'actor_id' => $this->admin->id, 'actor' => 'Administrator', 'disk' => 'r2',
        'path' => 'restore-test/backup.zip', 'files' => false,
    ]);
    $this->journal->ubah($id, ['status' => 'gagal', 'error' => 'Arsip tidak valid']);
    app()->instance(CatatanRestore::class, $this->journal);
    $this->actingAs($this->admin);

    Livewire::test(RiwayatRestore::class)->assertSee('Arsip tidak valid')->assertSee('Gagal')->assertSee('Cloudflare R2');
});
