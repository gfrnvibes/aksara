<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PemulihanDatabase
{
    public function validasi(Connection $connection): void
    {
        if ($connection->getDriverName() === 'pgsql') {
            if ($connection->getConfig('search_path') !== 'public') {
                throw new RuntimeException('Restore PostgreSQL hanya mendukung schema public.');
            }
            $this->psql($connection);
        } elseif ($connection->getDriverName() === 'sqlite') {
            if (! is_file($connection->getDatabaseName())) {
                throw new RuntimeException('Restore SQLite memerlukan database berbasis file.');
            }
        } else {
            throw new RuntimeException('Restore mendukung PostgreSQL dan SQLite.');
        }
    }

    public function pulihkan(Connection $connection, string $sql): void
    {
        $this->validasi($connection);
        if ($connection->getDriverName() === 'pgsql') {
            $prelude = dirname($sql).'/prepare.sql';
            file_put_contents($prelude, 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;');
            $process = new Process([
                $this->psql($connection), '--no-psqlrc', '--no-password', '--single-transaction',
                '--set=ON_ERROR_STOP=1', '--host='.$connection->getConfig('host'),
                '--port='.($connection->getConfig('port') ?: 5432),
                '--username='.$connection->getConfig('username'), '--dbname='.$connection->getDatabaseName(),
                '--file='.$prelude, '--file='.$sql,
            ], null, [
                'PGPASSWORD' => (string) $connection->getConfig('password'),
                'PGSSLMODE' => (string) ($connection->getConfig('sslmode') ?: 'prefer'),
            ], null, 1800);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Restore PostgreSQL gagal: '.substr($process->getErrorOutput(), -2000));
            }
            $connection->disconnect();
        } else {
            $this->sqlite($connection, $sql);
        }
    }

    protected function psql(Connection $connection): string
    {
        $directory = $connection->getConfig('dump')['dump_binary_path'] ?? '';
        $executable = (new ExecutableFinder)->find('psql', null, $directory ? [$directory] : []);
        if ($executable === null) {
            throw new RuntimeException('psql tidak ditemukan. Isi DB_DUMP_BINARY_PATH atau tambahkan psql ke PATH PHP.');
        }

        return $executable;
    }

    protected function sqlite(Connection $connection, string $sql): void
    {
        $target = $connection->getDatabaseName();
        $stage = $target.'.restore-'.bin2hex(random_bytes(8));
        $previous = $stage.'.previous';
        $installed = false;
        try {
            $pdo = new PDO('sqlite:'.$stage, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec(File::get($sql));
            $integrity = $pdo->query('PRAGMA integrity_check');
            if ($integrity === false || $integrity->fetchColumn() !== 'ok') {
                throw new RuntimeException('Integritas database SQLite hasil restore gagal.');
            }
            $integrity = null;
            $pdo = null;
            $connection->statement('PRAGMA wal_checkpoint(TRUNCATE)');
            DB::purge($connection->getName());
            if (! rename($target, $previous)) {
                throw new RuntimeException('Database SQLite sedang digunakan atau tidak dapat diganti.');
            }
            if (! rename($stage, $target)) {
                rename($previous, $target);
                throw new RuntimeException('Database SQLite hasil restore tidak dapat dipasang.');
            }
            $installed = true;
        } finally {
            $integrity = null;
            $pdo = null;
            if (is_file($stage)) {
                unlink($stage);
            }
            if ($installed && is_file($previous)) {
                unlink($previous);
            }
        }
    }
}
