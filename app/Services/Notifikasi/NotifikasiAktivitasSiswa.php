<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiSiswa;
use App\Models\Pengumuman;
use App\Models\PushToken;
use App\Models\Siswa;
use App\Models\User;

class NotifikasiAktivitasSiswa
{
    public function __construct(protected KlienExpoPush $expoPush) {}

    /** @param array<string, mixed> $data */
    public function kirimKeSiswa(int $siswaId, string $kategori, string $judul, string $isi, array $data = []): void
    {
        NotifikasiSiswa::query()->create([
            'siswa_id' => $siswaId,
            'kategori' => $kategori,
            'judul' => $judul,
            'isi' => $isi,
            'data' => $data === [] ? null : $data,
        ]);

        $tokens = PushToken::query()
            ->whereHas('user', fn ($q) => $q->where('siswa_id', $siswaId))
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $messages = array_values(array_map(fn (string $token): array => [
            'to' => $token,
            'title' => $judul,
            'body' => $isi,
            'sound' => 'default',
            'data' => [...$data, 'kategori' => $kategori],
        ], $tokens));

        $this->expoPush->kirim($messages);
    }

    public function kirimPengumuman(Pengumuman $pengumuman): void
    {
        if (! $pengumuman->is_aktif) {
            return;
        }

        $body = mb_strimwidth(trim($pengumuman->isi), 0, 140, '...');
        $tokens = [];

        if (in_array($pengumuman->tujuan, ['siswa', 'semua'], true)) {
            $now = now();
            $siswaIds = Siswa::query()->aktif()->pluck('id')->all();
            $rows = array_map(fn (int $siswaId): array => [
                'siswa_id' => $siswaId,
                'kategori' => 'pengumuman',
                'judul' => 'Pengumuman: '.$pengumuman->judul,
                'isi' => $body,
                'data' => json_encode(['pengumuman_id' => $pengumuman->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ], $siswaIds);

            foreach (array_chunk($rows, 200) as $chunk) {
                NotifikasiSiswa::query()->insert($chunk);
            }

            $tokens = [
                ...$tokens,
                ...PushToken::query()
                    ->whereHas('user', fn ($q) => $q->whereNotNull('siswa_id'))
                    ->pluck('token')
                    ->all(),
            ];
        }

        if (in_array($pengumuman->tujuan, ['guru', 'semua'], true)) {
            $guruUserIds = User::query()
                ->whereHas('gtk', fn ($q) => $q->where('is_aktif', true))
                ->pluck('id')
                ->all();

            if ($guruUserIds !== []) {
                $tokens = [
                    ...$tokens,
                    ...PushToken::query()
                        ->whereIn('user_id', $guruUserIds)
                        ->pluck('token')
                        ->all(),
                ];
            }
        }

        $tokens = array_values(array_unique($tokens));
        if ($tokens === []) {
            return;
        }

        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => 'Pengumuman: '.$pengumuman->judul,
            'body' => $body,
            'sound' => 'default',
            'data' => ['kategori' => 'pengumuman', 'pengumuman_id' => $pengumuman->id],
        ], $tokens);

        $this->expoPush->kirim($messages);
    }
}
