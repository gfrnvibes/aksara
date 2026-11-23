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
     * Download an official PDF (SIBI/Kemenag) by streaming directly to disk, or import from a local PDF path.
     */
    public function unduhPdfKeStorage(string $url, string $judul): string
    {
        if (is_file($url)) {
            return $this->simpanPdfStreamKeStorage($url, $judul, $url);
        }

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

            $contentType = strtolower((string) $response->header('Content-Type'));
            $isContentTypePdf = str_contains($contentType, 'pdf');

            return $this->simpanPdfStreamKeStorage($tempPath, $judul, $url, $isContentTypePdf);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    protected function simpanPdfStreamKeStorage(
        string $localPath,
        string $judul,
        string $hashSource,
        bool $isContentTypePdf = false,
    ): string {
        $handle = fopen($localPath, 'rb');
        $headerBytes = $handle !== false ? (string) fread($handle, 5) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        if (! $isContentTypePdf && ! str_starts_with($headerBytes, '%PDF')) {
            throw ValidationException::withMessages([
                'sumber_url' => 'URL atau file yang diberikan bukan file PDF yang valid.',
            ]);
        }

        $slug = str($judul)->slug()->limit(60, '');
        $filename = "perpustakaan/pdf/{$slug}-".substr(sha1($hashSource.microtime()), 0, 8).'.pdf';
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages([
                'sumber_url' => 'Gagal membaca file PDF.',
            ]);
        }

        try {
            Storage::disk(BukuPerpustakaan::diskName())->put($filename, $stream);
        } finally {
            fclose($stream);
        }

        return $filename;
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
                if ($filePdf === null || ! Storage::disk(BukuPerpustakaan::diskName())->exists($filePdf)) {
                    $filePdf = $this->unduhPdfKeStorage($pdfUrl, $judul);
                }

                $sampul = $existing?->sampul;
                $sampulUrl = trim((string) ($row['sampul_url'] ?? $row['sampul'] ?? ''));
                if (blank($sampul) && $sampulUrl !== '') {
                    $sampul = $this->unduhSampulKeStorage($sampulUrl, $judul);
                }
                if (blank($sampul) && $isbn !== null) {
                    $foundCover = app(CariMetadataIsbn::class)->cariSampulUrl($isbn);
                    if ($foundCover !== null) {
                        $sampul = $this->unduhSampulKeStorage($foundCover, $judul);
                    }
                }

                $mapelId = null;
                if (! empty($row['mapel'])) {
                    $mapelId = MataPelajaran::query()
                        ->where('nama', 'like', '%'.trim((string) $row['mapel']).'%')
                        ->value('id');
                }

                $kategori = strtolower(trim((string) ($row['kategori'] ?? 'paket')));
                if (! array_key_exists($kategori, BukuPerpustakaan::KATEGORI)) {
                    $kategori = 'paket';
                }

                $sumber = strtolower(trim((string) ($row['sumber'] ?? 'sibi')));
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
                        'sampul' => $sampul,
                        'file_pdf' => $filePdf,
                        'sumber_url' => trim((string) ($row['sumber_url'] ?? '')) ?: $pdfUrl,
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

    public function unduhSampulKeStorage(string $url, string $judul): ?string
    {
        try {
            $res = Http::timeout(15)->get($url);
            if (! $res->successful()) {
                return null;
            }
            $contentType = strtolower((string) $res->header('Content-Type'));
            if (! str_contains($contentType, 'image')) {
                return null;
            }
            $ext = str_contains($contentType, 'png') ? 'png' : 'jpg';
            $slug = str($judul)->slug()->limit(50, '');
            $filename = "perpustakaan/sampul/{$slug}-".substr(sha1($url), 0, 8).".{$ext}";
            Storage::disk(BukuPerpustakaan::sampulDiskName())->put($filename, $res->body());

            return $filename;
        } catch (Throwable) {
            return null;
        }
    }
}
