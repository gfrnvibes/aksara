<?php

namespace App\Http\Controllers;

use App\Models\GaleriKegiatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GaleriKomentarController extends Controller
{
    public function store(Request $request, GaleriKegiatan $galeri): JsonResponse
    {
        abort_unless($galeri->is_publik, 404);

        $validated = $request->validate([
            'nama_pengomentar' => ['required', 'string', 'min:2', 'max:100'],
            'peran' => ['nullable', 'string', 'max:50'],
            'isi' => ['required', 'string', 'min:2', 'max:500'],
        ], [
            'nama_pengomentar.required' => 'Nama wajib diisi.',
            'nama_pengomentar.min' => 'Nama minimal 2 karakter.',
            'isi.required' => 'Komentar tidak boleh kosong.',
            'isi.min' => 'Komentar terlalu singkat.',
            'isi.max' => 'Komentar maksimal 500 karakter.',
        ]);

        $komentar = $galeri->komentar()->create([
            'nama_pengomentar' => trim(strip_tags($validated['nama_pengomentar'])),
            'peran' => filled($validated['peran'] ?? null) ? trim(strip_tags((string) $validated['peran'])) : 'Pengunjung',
            'isi' => trim(strip_tags($validated['isi'])),
            'is_tampil' => true,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'komentar' => [
                'id' => $komentar->id,
                'nama_pengomentar' => $komentar->nama_pengomentar,
                'peran' => $komentar->peran,
                'isi' => $komentar->isi,
                'waktu' => 'Baru saja',
            ],
        ], 201);
    }
}
