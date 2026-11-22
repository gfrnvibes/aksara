<?php

namespace App\Http\Controllers;

use App\Models\BukuPerpustakaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PerpustakaanPublikController extends Controller
{
    public function index(Request $request): View
    {
        $kataKunci = $request->string('q')->trim()->toString();
        $kategoriAktif = $request->string('kategori')->trim()->toString();
        $tingkatAktif = $request->integer('tingkat') ?: null;

        $paginator = BukuPerpustakaan::query()
            ->aktif()
            ->with('mataPelajaran')
            ->when($kataKunci !== '', function ($q) use ($kataKunci): void {
                $q->where(function ($sub) use ($kataKunci): void {
                    $sub->where('judul', 'like', "%{$kataKunci}%")
                        ->orWhere('penulis', 'like', "%{$kataKunci}%")
                        ->orWhere('penerbit', 'like', "%{$kataKunci}%")
                        ->orWhere('isbn', 'like', "%{$kataKunci}%");
                });
            })
            ->when($kategoriAktif !== '', fn ($q) => $q->where('kategori', $kategoriAktif))
            ->when($tingkatAktif !== null, fn ($q) => $q->where('tingkat', $tingkatAktif))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('perpustakaan', [
            'sekolah' => config('sekolah'),
            'paginator' => $paginator,
            'kataKunci' => $kataKunci,
            'kategoriAktif' => $kategoriAktif,
            'tingkatAktif' => $tingkatAktif,
            'daftarKategori' => BukuPerpustakaan::KATEGORI,
            'daftarTingkat' => BukuPerpustakaan::TINGKAT,
        ]);
    }

    public function viewer(BukuPerpustakaan $buku): View
    {
        abort_unless($buku->is_aktif, 404);
        abort_unless(Storage::disk('local')->exists($buku->file_pdf), 404);

        return view('perpustakaan-viewer', [
            'buku' => $buku,
            'pdfUrl' => route('perpustakaan.baca', $buku),
        ]);
    }

    public function baca(BukuPerpustakaan $buku): BinaryFileResponse
    {
        abort_unless($buku->is_aktif, 404);
        abort_unless(Storage::disk('local')->exists($buku->file_pdf), 404);

        $buku->increment('jumlah_dibaca');

        return response()->file(
            Storage::disk('local')->path($buku->file_pdf),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.str($buku->judul)->slug().'.pdf"',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}
