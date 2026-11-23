<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BukuPerpustakaan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PerpustakaanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $kataKunci = trim((string) $request->query('q', ''));
        $kategori = trim((string) $request->query('kategori', ''));
        $tingkat = $request->query('tingkat') ? (int) $request->query('tingkat') : null;

        $rows = BukuPerpustakaan::query()
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
            ->when($kategori !== '', fn ($q) => $q->where('kategori', $kategori))
            ->when($tingkat !== null, fn ($q) => $q->where('tingkat', $tingkat))
            ->urutkanUtama()
            ->limit(60)
            ->get();

        $buku = $rows->map(fn (BukuPerpustakaan $b): array => [
            'id' => $b->id,
            'judul' => $b->judul,
            'penulis' => $b->penulis,
            'penerbit' => $b->penerbit,
            'tahun_terbit' => $b->tahun_terbit,
            'isbn' => $b->isbn,
            'kategori' => $b->kategori,
            'kategori_label' => BukuPerpustakaan::KATEGORI[$b->kategori] ?? ucfirst($b->kategori),
            'tingkat' => $b->tingkat,
            'tingkat_label' => $b->tingkat !== null ? (BukuPerpustakaan::TINGKAT[$b->tingkat] ?? "Kelas {$b->tingkat}") : 'Semua Tingkat',
            'mapel' => $b->mataPelajaran?->nama,
            'deskripsi' => $b->deskripsi,
            'sampul_url' => $b->sampulUrl(),
            'jumlah_dibaca' => $b->jumlah_dibaca,
            'baca_url' => URL::temporarySignedRoute('api.perpustakaan.viewer', now()->addHours(2), ['buku' => $b->id]),
        ])->all();

        return response()->json([
            'kategori_opsi' => BukuPerpustakaan::KATEGORI,
            'tingkat_opsi' => BukuPerpustakaan::TINGKAT,
            'buku' => $buku,
        ]);
    }

    public function viewer(BukuPerpustakaan $buku): View
    {
        abort_unless($buku->is_aktif, 404);
        $disk = BukuPerpustakaan::diskName();
        abort_unless(Storage::disk($disk)->exists($buku->file_pdf), 404);

        $pdfUrl = URL::temporarySignedRoute('api.perpustakaan.baca', now()->addHours(2), ['buku' => $buku->id]);
        $proxyPdfUrl = URL::temporarySignedRoute('api.perpustakaan.baca', now()->addHours(2), ['buku' => $buku->id, 'proxy' => 1]);
        if (BukuPerpustakaan::adalahCloudDisk($disk)) {
            $buku->increment('jumlah_dibaca');
            $pdfUrl = Storage::disk($disk)->temporaryUrl($buku->file_pdf, now()->addHours(2), [
                'ResponseContentType' => 'application/pdf',
            ]);
        }

        return view('perpustakaan-viewer', [
            'buku' => $buku,
            'pdfUrl' => $pdfUrl,
            'proxyPdfUrl' => $proxyPdfUrl,
        ]);
    }

    public function baca(Request $request, BukuPerpustakaan $buku): Response
    {
        abort_unless($buku->is_aktif, 404);
        $disk = BukuPerpustakaan::diskName();
        abort_unless(Storage::disk($disk)->exists($buku->file_pdf), 404);

        $isProxy = $request->boolean('proxy');
        if (! $isProxy) {
            $buku->increment('jumlah_dibaca');
        }

        if (BukuPerpustakaan::adalahCloudDisk($disk)) {
            if ($isProxy) {
                $stream = Storage::disk($disk)->readStream($buku->file_pdf);
                abort_if($stream === null, 404);

                return response()->stream(function () use ($stream): void {
                    fpassthru($stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.str($buku->judul)->slug().'.pdf"',
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }

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
}
