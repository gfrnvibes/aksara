<?php

namespace App\Services\Perpustakaan;

use App\Models\BukuPerpustakaan;
use App\Models\MataPelajaran;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImporBukuPerpustakaan
{
    /**
     * Download an official PDF (SIBI/Kemenag) by streaming directly to disk.
     */
    public function unduhPdfKeStorage(string $url, string $judul): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'aksara_pdf_');
        if ($tempPath === false) {
            throw ValidationException::withMessages([
                'sumber_url' => 'Gagal membuat file sementara untuk mengunduh PDF.',
            ]);
        }

        try {
            $response = Http::timeout(180)
                ->connectTimeout(20)
                ->sink($tempPath)
                ->get($url);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'sumber_url' => "Gagal mengunduh file PDF dari URL ({$response->status()}).",
                ]);
            }

            $handle = fopen($tempPath, 'rb');
            $headerBytes = $handle !== false ? (string) fread($handle, 5) : '';
            if ($handle !== false) {
                fclose($handle);
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (! str_contains($contentType, 'pdf') && ! str_starts_with($headerBytes, '%PDF')) {
                throw ValidationException::withMessages([
                    'sumber_url' => 'URL yang diberikan bukan file PDF yang valid.',
                ]);
            }

            $slug = str($judul)->slug()->limit(60, '');
            $filename = "perpustakaan/pdf/{$slug}-".substr(sha1($url.microtime()), 0, 8).'.pdf';
            $stream = fopen($tempPath, 'rb');
            if ($stream === false) {
                throw ValidationException::withMessages([
                    'sumber_url' => 'Gagal membaca file PDF yang telah diunduh.',
                ]);
            }

            try {
                Storage::disk('local')->put($filename, $stream);
            } finally {
                fclose($stream);
            }

            return $filename;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Import curated book rows (restricted to MTs grades VII, VIII, IX or general) into local catalog.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{berhasil: int, dilewati: int, gagal: list<string>}
     */
    public function imporDariBaris(array $rows, ?int $userId = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $imported = 0;
        $skipped = 0;
        $failed = [];

        foreach ($rows as $row) {
            $judul = trim((string) ($row['judul'] ?? ''));
            $pdfUrl = trim((string) ($row['pdf_url'] ?? $row['sumber_url'] ?? ''));
            if ($judul === '' || $pdfUrl === '') {
                $skipped++;

                continue;
            }

            $tingkat = isset($row['tingkat']) && $row['tingkat'] !== '' ? (int) $row['tingkat'] : null;
            if ($tingkat !== null && ! in_array($tingkat, [7, 8, 9], true)) {
                // Keep only MTs grades (VII, VIII, IX).
                $skipped++;

                continue;
            }

            try {
                $isbn = isset($row['isbn']) && trim((string) $row['isbn']) !== '' ? trim((string) $row['isbn']) : null;
                $existing = BukuPerpustakaan::query()
                    ->when($isbn !== null, fn ($q) => $q->where('isbn', $isbn))
                    ->when($isbn === null, fn ($q) => $q->where('judul', $judul)->where('tingkat', $tingkat))
                    ->first();

                $filePdf = $existing?->file_pdf;
                if ($filePdf === null || ! Storage::disk('local')->exists($filePdf)) {
                    $filePdf = $this->unduhPdfKeStorage($pdfUrl, $judul);
                }

                $mapelId = null;
                if (! empty($row['mapel'])) {
                    $mapelId = MataPelajaran::query()
                        ->where('nama', 'like', '%'.trim((string) $row['mapel']).'%')
                        ->value('id');
                }

                $kategori = (string) ($row['kategori'] ?? 'paket');
                if (! array_key_exists($kategori, BukuPerpustakaan::KATEGORI)) {
                    $kategori = 'paket';
                }

                $sumber = (string) ($row['sumber'] ?? 'sibi');
                if (! array_key_exists($sumber, BukuPerpustakaan::SUMBER)) {
                    $sumber = 'sibi';
                }

                BukuPerpustakaan::query()->updateOrCreate(
                    $isbn !== null ? ['isbn' => $isbn] : ['judul' => $judul, 'tingkat' => $tingkat],
                    [
                        'judul' => $judul,
                        'penulis' => isset($row['penulis']) ? trim((string) $row['penulis']) : null,
                        'penerbit' => isset($row['penerbit']) ? trim((string) $row['penerbit']) : null,
                        'tahun_terbit' => isset($row['tahun_terbit']) && is_numeric($row['tahun_terbit']) ? (int) $row['tahun_terbit'] : null,
                        'kategori' => $kategori,
                        'sumber' => $sumber,
                        'tingkat' => $tingkat,
                        'mata_pelajaran_id' => $mapelId,
                        'deskripsi' => isset($row['deskripsi']) ? trim((string) $row['deskripsi']) : null,
                        'file_pdf' => $filePdf,
                        'sumber_url' => $pdfUrl,
                        'is_aktif' => true,
                        'pengunggah_id' => $userId,
                    ],
                );

                $imported++;
            } catch (Throwable $e) {
                $failed[] = "{$judul}: {$e->getMessage()}";
            }
        }

        return [
            'berhasil' => $imported,
            'dilewati' => $skipped,
            'gagal' => $failed,
        ];
    }
}
