<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $role = $user->siswa_id !== null ? 'siswa' : 'guru';

        $items = Pengumuman::query()
            ->with('pembuat')
            ->aktifUntuk($role)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Pengumuman $p): array => [
                'id' => $p->id,
                'judul' => $p->judul,
                'isi' => $p->isi,
                'tujuan' => $p->tujuan,
                'tujuan_label' => Pengumuman::TUJUAN[$p->tujuan] ?? ucfirst($p->tujuan),
                'tanggal_mulai' => $p->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai' => $p->tanggal_selesai?->format('Y-m-d'),
                'pembuat' => $p->pembuat?->name,
                'created_at' => $p->created_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
            ])
            ->all();

        return response()->json([
            'pengumuman' => $items,
        ]);
    }
}
