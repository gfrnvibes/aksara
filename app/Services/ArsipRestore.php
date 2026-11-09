<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class ArsipRestore
{
    /** @return array{sql: string, files: string, has_files: bool} */
    public function ekstrak(string $archive, string $directory, string $driver): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Arsip ZIP tidak dapat dibuka.');
        }
        $zip->setPassword((string) config('backup.backup.password'));
        $sql = null;
        $hasFiles = false;
        $seen = [];
        $total = 0;
        $prefix = match ($driver) {
            'pgsql' => 'postgresql-', 'sqlite' => 'sqlite-',
            default => throw new RuntimeException('Restore mendukung PostgreSQL dan SQLite.'),
        };

        try {
            if ($zip->numFiles > 100000) {
                throw new RuntimeException('Arsip melebihi batas jumlah file.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new RuntimeException('Metadata arsip tidak valid.');
                }
                $name = str_replace('\\', '/', $stat['name']);
                $key = strtolower($name);
                if (str_starts_with($name, '/') || str_contains($name, "\0") || str_contains($name, ':')
                    || in_array('..', explode('/', $name), true) || in_array('.', explode('/', $name), true)
                    || isset($seen[$key])) {
                    throw new RuntimeException('Arsip memuat path tidak aman atau duplikat.');
                }
                $seen[$key] = true;
                $opsys = $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Symlink tidak diizinkan dalam arsip restore.');
                }
                $total += $stat['size'];
                if ($total > 2147483648) {
                    throw new RuntimeException('Isi arsip melebihi batas 2 GB.');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }

                if (str_starts_with($name, 'db-dumps/'.$prefix) && str_ends_with($name, '.sql') && substr_count($name, '/') === 1) {
                    if ($sql !== null || $stat['size'] > 134217728) {
                        throw new RuntimeException('Arsip harus berisi satu dump database, maksimal 128 MB.');
                    }
                    $destination = $sql = $directory.'/database.sql';
                } elseif (str_starts_with($name, 'storage/app/public/')) {
                    $hasFiles = true;
                    $destination = $directory.'/files/'.substr($name, strlen('storage/app/public/'));
                } else {
                    throw new RuntimeException('Arsip tidak sesuai: hanya dump database dan file storage/app/public yang didukung.');
                }

                File::ensureDirectoryExists(dirname($destination));
                $input = $zip->getStream($stat['name']);
                $output = fopen($destination, 'wb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                    throw new RuntimeException('File arsip tidak dapat dibaca. Periksa password arsip dan izin penyimpanan.');
                }
                try {
                    $written = stream_copy_to_stream($input, $output, $stat['size'] + 1);
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if ($written !== $stat['size'] || hash_file('crc32b', $destination) !== sprintf('%08x', $stat['crc'])) {
                    throw new RuntimeException('Integritas arsip gagal. File rusak atau password arsip salah.');
                }
            }
            if ($sql === null) {
                throw new RuntimeException('Dump database yang cocok tidak ditemukan dalam arsip.');
            }
            File::ensureDirectoryExists($directory.'/files');

            return ['sql' => $sql, 'files' => $directory.'/files', 'has_files' => $hasFiles];
        } finally {
            $zip->close();
        }
    }
}
