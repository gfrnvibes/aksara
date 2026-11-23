<?php

namespace App\Http\Controllers;

use App\Models\BukuPerpustakaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
            ->urutkanUtama()
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

    public function sampul(BukuPerpustakaan $buku): Response
    {
        abort_unless($buku->is_aktif && filled($buku->sampul), 404);
        $disk = BukuPerpustakaan::sampulDiskName();
        $path = (string) $buku->sampul;
        abort_unless(Storage::disk($disk)->exists($path), 404);

        if (BukuPerpustakaan::adalahCloudDisk($disk)) {
            return redirect()->away(Storage::disk($disk)->temporaryUrl($path, now()->addHours(6)));
        }

        return response()->file(Storage::disk($disk)->path($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function viewer(BukuPerpustakaan $buku): View
    {
        abort_unless($buku->is_aktif, 404);
        $disk = BukuPerpustakaan::diskName();
        abort_unless(Storage::disk($disk)->exists($buku->file_pdf), 404);

        $pdfUrl = route('perpustakaan.baca', $buku);
        if (BukuPerpustakaan::adalahCloudDisk($disk)) {
            $buku->increment('jumlah_dibaca');
            $pdfUrl = Storage::disk($disk)->temporaryUrl($buku->file_pdf, now()->addHours(2), [
                'ResponseContentType' => 'application/pdf',
            ]);
        }

        return view('perpustakaan-viewer', [
            'buku' => $buku,
            'pdfUrl' => $pdfUrl,
        ]);
    }

    public function baca(Request $request, BukuPerpustakaan $buku): Response
    {
        abort_unless($buku->is_aktif, 404);
        $disk = BukuPerpustakaan::diskName();
        abort_unless(Storage::disk($disk)->exists($buku->file_pdf), 404);

        if (! $request->boolean('thumb')) {
            $buku->increment('jumlah_dibaca');
        }

        if (BukuPerpustakaan::adalahCloudDisk($disk)) {
            return redirect()->away(
                Storage::disk($disk)->temporaryUrl($buku->file_pdf, now()->addHours(2), [
                    'ResponseContentType' => 'application/pdf',
                    'ResponseContentDisposition' => 'inline; filename="'.str($buku->judul)->slug().'.pdf"',
                ]),
            );
        }

        return response()->file(
            Storage::disk($disk)->path($buku->file_pdf),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.str($buku->judul)->slug().'.pdf"',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    public function simpanSampulOtomatis(Request $request, BukuPerpustakaan $buku): JsonResponse
    {
        abort_unless($buku->is_aktif, 404);
        $disk = BukuPerpustakaan::sampulDiskName();
        if (filled($buku->sampul) && Storage::disk($disk)->exists((string) $buku->sampul)) {
            return response()->json(['sampul_url' => $buku->sampulUrl()]);
        }

        $data = $request->validate([
            'image' => ['required', 'string'],
        ]);

        $raw = (string) $data['image'];
        if (! str_starts_with($raw, 'data:image/jpeg;base64,')) {
            return response()->json(['message' => 'Format gambar tidak didukung.'], 422);
        }

        $binary = base64_decode(substr($raw, strlen('data:image/jpeg;base64,')), true);
        if ($binary === false || strlen($binary) > 512000 || @getimagesizefromstring($binary) === false) {
            return response()->json(['message' => 'Data gambar tidak valid.'], 422);
        }

        $path = "perpustakaan/sampul/buku-{$buku->id}.jpg";
        Storage::disk($disk)->put($path, $binary);
        $buku->update(['sampul' => $path]);

        return response()->json(['sampul_url' => $buku->sampulUrl()]);
    }
}
