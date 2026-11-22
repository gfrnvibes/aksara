<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PertemuanMengajar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruNotifikasiController extends Controller
{
    public function simpanToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGuru = $user instanceof User && $user->gtk !== null && $user->gtk->is_aktif;
        $isSiswa = $user instanceof User && $user->siswa_id !== null;
        abort_unless($isGuru || $isSiswa, 403);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        PushToken::query()->updateOrCreate(
            ['token' => (string) $data['token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json(['message' => 'Token notifikasi tersimpan.']);
    }

    public function hapusToken(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        PushToken::query()
            ->where('user_id', $user->id)
            ->where('token', (string) $data['token'])
            ->delete();

        return response()->json(['message' => 'Token notifikasi dihapus.']);
    }

    public function sesiHariIni(Request $request, PertemuanMengajar $pertemuan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $tanggal = AksesHarian::hariIni();
        $daftar = $pertemuan->daftar($user, $tanggal, false)
            ->values()
            ->map(fn (array $item): array => [
                'sumber_jadwal' => (string) $item['sumber_jadwal'],
                'kelas' => (string) $item['nama_kelas'],
                'mapel' => (string) $item['nama_mapel'],
                'jp_mulai' => (int) $item['jp_mulai'],
                'jp_selesai' => (int) $item['jp_selesai'],
                'mulai' => $item['mulai'] ?? null,
                'waktu' => $item['waktu'] ?? null,
            ])
            ->all();

        return response()->json([
            'tanggal' => $tanggal,
            'sesi' => $daftar,
        ]);
    }
}
