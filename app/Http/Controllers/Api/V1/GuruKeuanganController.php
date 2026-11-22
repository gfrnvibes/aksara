<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GajiGuru;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruKeuanganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $rows = GajiGuru::query()
            ->where('gtk_id', $user->gtk->id)
            ->with(['pembayaran' => fn ($q) => $q->orderByDesc('tanggal')])
            ->orderByDesc('periode')
            ->orderByDesc('id')
            ->get();

        $totalDiterima = 0;
        $totalBelumDibayar = 0;
        $slip = [];

        foreach ($rows as $row) {
            $bersih = $row->bersih();
            $sudahDibayar = $row->pembayaran->isNotEmpty();

            if ($sudahDibayar) {
                $totalDiterima += $bersih;
            } else {
                $totalBelumDibayar += $bersih;
            }

            $slip[] = [
                'id' => $row->id,
                'periode' => $row->periode,
                'gaji_pokok' => $row->gaji_pokok,
                'tunjangan' => $row->tunjangan,
                'potongan' => $row->potongan,
                'bersih' => $bersih,
                'sudah_dibayar' => $sudahDibayar,
                'tanggal_bayar' => $row->pembayaran->first()?->tanggal?->format('Y-m-d'),
                'catatan' => $row->catatan,
            ];
        }

        return response()->json([
            'ringkasan' => [
                'total_diterima' => $totalDiterima,
                'total_belum_dibayar' => $totalBelumDibayar,
                'jumlah_slip' => count($slip),
            ],
            'slip' => $slip,
        ]);
    }
}
