<?php

namespace App\Http\Controllers;

use App\Models\GaleriKegiatan;
use App\Models\GaleriKomentar;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GaleriPublikController extends Controller
{
    public function __invoke(Request $request): View
    {
        $kategoriAktif = $request->string('kategori')->trim()->toString();

        $daftarKategori = GaleriKegiatan::query()
            ->publik()
            ->whereNotNull('kategori')
            ->distinct()
            ->orderBy('kategori')
            ->pluck('kategori');

        $paginator = GaleriKegiatan::query()
            ->publik()
            ->when($kategoriAktif !== '', fn ($q) => $q->where('kategori', $kategoriAktif))
            ->with('komentarTampil')
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();

        $galeriKegiatan = $paginator->getCollection()
            ->map(function (GaleriKegiatan $item): array {
                return [
                    'id' => $item->id,
                    'judul' => $item->judul,
                    'kategori' => $item->kategori ?: 'Umum',
                    'tanggal' => $item->tanggal_kegiatan?->translatedFormat('d M Y') ?? $item->created_at?->translatedFormat('d M Y') ?? '',
                    'deskripsi' => $item->deskripsi,
                    'media' => $item->mediaUntukTampil(),
                    'komentar' => $item->komentarTampil->map(fn (GaleriKomentar $k): array => [
                        'id' => $k->id,
                        'nama_pengomentar' => $k->nama_pengomentar,
                        'peran' => $k->peran ?: 'Pengunjung',
                        'isi' => $k->isi,
                        'waktu' => $k->created_at?->diffForHumans() ?? '',
                    ])->values()->all(),
                ];
            })
            ->filter(fn (array $item): bool => $item['media'] !== [])
            ->values();

        return view('galeri', [
            'sekolah' => config('sekolah'),
            'daftarKategori' => $daftarKategori,
            'kategoriAktif' => $kategoriAktif,
            'paginator' => $paginator,
            'galeriKegiatan' => $galeriKegiatan,
        ]);
    }
}
