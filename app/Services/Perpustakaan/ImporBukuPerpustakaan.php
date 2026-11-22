<?php

namespace App\Services\Perpustakaan;

use App\Models\BukuPerpustakaan;
use App\Models\MataPelajaran;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImporBukuPerpustakaan
{
    /**
     * Download an official PDF (SIBI/Kemenag) into Aksara's private storage.
     */
    public function unduhPdfKeStorage(string $url, string $judul): string
    {
        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'sumber_url' => "Gagal mengunduh file PDF dari URL ({$response->status()}).",
            ]);
        }

        $body = $response->body();
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'pdf') && ! str_starts_with($body, '%PDF')) {
            throw ValidationException::withMessages([
                'sumber_url' => 'URL yang diberikan bukan file PDF yang valid.',
            ]);
        }

        $slug = str($judul)->slug()->limit(60, '');
        $filename = "perpustakaan/pdf/{$slug}-".substr(sha1($url.microtime()), 0, 8).'.pdf';
        Storage::disk('local')->put($filename, $body);

        return $filename;
    }

    /**
     * Import curated book rows (restricted to MTs grades VII, VIII, IX or general) into local catalog.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function imporDariBaris(array $rows, ?int $userId = null): int
    {
        $imported = 0;

        foreach ($rows as $row) {
            $judul = trim((string) ($row['judul'] ?? ''));
            $pdfUrl = trim((string) ($row['pdf_url'] ?? $row['sumber_url'] ?? ''));
            if ($judul === '' || $pdfUrl === '') {
                continue;
            }

            $tingkat = isset($row['tingkat']) && $row['tingkat'] !== '' ? (int) $row['tingkat'] : null;
            if ($tingkat !== null && ! in_array($tingkat, [7, 8, 9], true)) {
                // Keep only MTs grades (VII, VIII, IX).
                continue;
            }

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
        }

        return $imported;
    }
}
