<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class CatatanRestore
{
    protected function directory(): string
    {
        return storage_path('app/private/restore-history');
    }

    /** @param array<string, mixed> $data */
    public function buat(array $data): string
    {
        $id = (string) Str::uuid();
        $this->simpan($id, [...$data, 'id' => $id, 'status' => 'menunggu', 'error' => null,
            'safety_path' => null, 'created_at' => now()->toDateTimeString(), 'finished_at' => null]);

        return $id;
    }

    /** @return array<string, mixed> */
    public function ambil(string $id): array
    {
        $data = json_decode(File::get($this->path($id)), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Riwayat restore tidak valid.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function ubah(string $id, array $data): void
    {
        $this->simpan($id, [...$this->ambil($id), ...$data]);
    }

    /** @param array<string, mixed> $data */
    protected function simpan(string $id, array $data): void
    {
        File::ensureDirectoryExists($this->directory());
        File::replace($this->path($id), json_encode($data, JSON_THROW_ON_ERROR), 0600);
    }

    protected function path(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new RuntimeException('ID restore tidak valid.');
        }

        return $this->directory().'/'.$id.'.json';
    }

    /** @return Collection<string, array<string, mixed>> */
    public function semua(): Collection
    {
        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        $records = [];
        foreach (File::files($this->directory()) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $id = $file->getBasename('.json');
            $records[$id] = $this->ambil($id);
        }

        return collect($records)->sortByDesc('created_at');
    }
}
