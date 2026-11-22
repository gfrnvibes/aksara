<?php

namespace App\Console\Commands;

use App\Services\Perpustakaan\ImporBukuPerpustakaan;
use Illuminate\Console\Command;

class ImporPerpustakaanCommand extends Command
{
    protected $signature = 'aksara:impor-perpustakaan {file : Path ke file JSON atau CSV katalog buku SMP/MTs}';

    protected $description = 'Impor katalog buku digital (SIBI/Kemenag) ke storage lokal Aksara (khusus jenjang MTs kelas VII-IX)';

    public function handle(ImporBukuPerpustakaan $importer): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'json') {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                $this->error('Format JSON tidak valid.');

                return self::FAILURE;
            }
            $rows = array_values(array_filter($decoded, 'is_array'));
        } elseif ($ext === 'csv') {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                $this->error('Gagal membuka file CSV.');

                return self::FAILURE;
            }
            $header = fgetcsv($handle);
            if (is_array($header)) {
                $keys = array_map(fn ($h): string => trim((string) $h), $header);
                while (($line = fgetcsv($handle)) !== false) {
                    if (count($line) === count($keys)) {
                        $rows[] = array_combine($keys, $line);
                    }
                }
            }
            fclose($handle);
        } else {
            $this->error('Format file harus .json atau .csv.');

            return self::FAILURE;
        }

        $count = $importer->imporDariBaris($rows);
        $this->info("Berhasil mengimpor {$count} buku ke perpustakaan digital Aksara.");

        return self::SUCCESS;
    }
}
