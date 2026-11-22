<?php

namespace App\Services\Perpustakaan;

use Illuminate\Support\Facades\Http;
use Throwable;

class CariMetadataIsbn
{
    /**
     * Look up book metadata by ISBN via Google Books API and Open Library fallback.
     *
     * @return array{judul: string|null, penulis: string|null, penerbit: string|null, tahun_terbit: int|null, deskripsi: string|null}|null
     */
    public function cari(string $isbn): ?array
    {
        $clean = preg_replace('/[^0-9Xx]/', '', $isbn);
        if (! is_string($clean) || $clean === '') {
            return null;
        }

        $google = $this->dariGoogleBooks($clean);
        if ($google !== null) {
            return $google;
        }

        return $this->dariOpenLibrary($clean);
    }

    /**
     * @return array{judul: string|null, penulis: string|null, penerbit: string|null, tahun_terbit: int|null, deskripsi: string|null}|null
     */
    protected function dariGoogleBooks(string $isbn): ?array
    {
        try {
            $res = Http::acceptJson()->timeout(8)->get('https://www.googleapis.com/books/v1/volumes', [
                'q' => "isbn:{$isbn}",
                'maxResults' => 1,
            ]);

            if (! $res->successful()) {
                return null;
            }

            $info = $res->json('items.0.volumeInfo');
            if (! is_array($info) || empty($info['title'])) {
                return null;
            }

            $authors = is_array($info['authors'] ?? null) ? implode(', ', $info['authors']) : null;
            $year = null;
            if (is_string($info['publishedDate'] ?? null) && preg_match('/\b(19|20)\d{2}\b/', $info['publishedDate'], $m)) {
                $year = (int) $m[0];
            }

            return [
                'judul' => is_string($info['title']) ? $info['title'] : null,
                'penulis' => $authors,
                'penerbit' => is_string($info['publisher'] ?? null) ? $info['publisher'] : null,
                'tahun_terbit' => $year,
                'deskripsi' => is_string($info['description'] ?? null) ? $info['description'] : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{judul: string|null, penulis: string|null, penerbit: string|null, tahun_terbit: int|null, deskripsi: string|null}|null
     */
    protected function dariOpenLibrary(string $isbn): ?array
    {
        try {
            $key = "ISBN:{$isbn}";
            $res = Http::acceptJson()->timeout(8)->get('https://openlibrary.org/api/books', [
                'bibkeys' => $key,
                'format' => 'json',
                'jscmd' => 'data',
            ]);

            if (! $res->successful()) {
                return null;
            }

            $info = $res->json($key);
            if (! is_array($info) || empty($info['title'])) {
                return null;
            }

            $authors = null;
            if (is_array($info['authors'] ?? null)) {
                $names = array_filter(array_map(fn ($a): ?string => is_array($a) && is_string($a['name'] ?? null) ? $a['name'] : null, $info['authors']));
                $authors = $names !== [] ? implode(', ', $names) : null;
            }

            $publisher = null;
            if (is_array($info['publishers'] ?? null) && is_array($info['publishers'][0] ?? null)) {
                $publisher = is_string($info['publishers'][0]['name'] ?? null) ? $info['publishers'][0]['name'] : null;
            }

            $year = null;
            if (is_string($info['publish_date'] ?? null) && preg_match('/\b(19|20)\d{2}\b/', $info['publish_date'], $m)) {
                $year = (int) $m[0];
            }

            return [
                'judul' => is_string($info['title']) ? $info['title'] : null,
                'penulis' => $authors,
                'penerbit' => $publisher,
                'tahun_terbit' => $year,
                'deskripsi' => null,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
