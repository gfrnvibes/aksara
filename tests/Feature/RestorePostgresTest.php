<?php

use App\Services\PemulihanDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

it('restores a pg_dump archive transactionally in a disposable local database', function () {
    if (! filter_var(getenv('RUN_RESTORE_PGSQL_TESTS'), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set RUN_RESTORE_PGSQL_TESTS=1 for the disposable localhost PostgreSQL integration test.');
    }
    $settings = config('database.connections.pgsql');
    if (! in_array($settings['host'], ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('This integration test only permits a localhost PostgreSQL server.');
    }
    $name = 'aksara_restore_test_'.bin2hex(random_bytes(8));
    $root = storage_path('framework/testing/'.$name);
    File::ensureDirectoryExists($root);
    config(['database.connections.restore_admin' => [...$settings, 'url' => null, 'database' => 'postgres']]);
    $admin = DB::connection('restore_admin');
    $created = false;

    try {
        // Never target the application's database: this name is generated here.
        if (! preg_match('/^aksara_restore_test_[a-f0-9]{16}$/D', $name)) {
            throw new RuntimeException('Invalid disposable database name.');
        }
        $admin->statement('CREATE DATABASE "'.$name.'"');
        $created = true;
        config(['database.connections.restore_pg_fixture' => [...$settings, 'url' => null, 'database' => $name]]);
        $connection = DB::connection('restore_pg_fixture');
        if ($connection->getDatabaseName() !== $name) {
            throw new RuntimeException('Restore target must be the disposable fixture database.');
        }
        $connection->statement('CREATE TABLE entries (value TEXT)');
        $connection->table('entries')->insert(['value' => 'backup']);
        $sql = $root.'/database.sql';
        DbDumperFactory::createFromConnection('restore_pg_fixture')->dumpToFile($sql);
        $connection->table('entries')->update(['value' => 'current']);

        $restore = new PemulihanDatabase;
        $restore->pulihkan($connection, $sql);
        expect($connection->table('entries')->value('value'))->toBe('backup');
        file_put_contents($root.'/bad.sql', 'CREATE TABLE broken (invalid syntax);');
        expect(fn () => $restore->pulihkan($connection, $root.'/bad.sql'))->toThrow(RuntimeException::class);
        expect($connection->table('entries')->value('value'))->toBe('backup');
    } finally {
        DB::purge('restore_pg_fixture');
        if ($created) {
            $admin->statement('DROP DATABASE "'.$name.'" WITH (FORCE)');
        }
        DB::purge('restore_admin');
        File::deleteDirectory($root);
    }
});
