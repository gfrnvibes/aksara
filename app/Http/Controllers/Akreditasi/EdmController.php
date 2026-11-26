<?php

namespace App\Http\Controllers\Akreditasi;

use App\Http\Controllers\Controller;
use App\Models\EdmIndikator;
use App\Models\User;
use App\Services\Akreditasi\KatalogEdm2026;
use App\Services\Akreditasi\PembuatBundelEdm;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class EdmController extends Controller
{
    public function cetakButir(Request $request, EdmIndikator $indikator): View
    {
        $this->otorisasi($request->user());

        $indikator->loadMissing(['evaluasi.tahunAjaran', 'buktis']);

        return view('cetak.edm-butir', [
            'sekolah' => config('sekolah'),
            'indikator' => $indikator,
            'definisi' => KatalogEdm2026::cari($indikator->kode),
            'daftarBukti' => $indikator->buktiGabungan(),
        ]);
    }

    public function unduhZipButir(Request $request, EdmIndikator $indikator, PembuatBundelEdm $bundel): BinaryFileResponse
    {
        $this->otorisasi($request->user());

        $zipPath = $bundel->buatZipButir($indikator);
        $tahun = str($indikator->evaluasi->tahunAjaran->nama ?? 'edm')->slug();

        return response()
            ->download($zipPath, "EDM-{$indikator->kode}-{$tahun}.zip")
            ->deleteFileAfterSend(true);
    }

    public function gambarBukti(Request $request, EdmIndikator $indikator, int $indeks): Response
    {
        $this->otorisasi($request->user());

        $item = $indikator->buktiGabungan()->values()->get($indeks);
        abort_if($item === null, 404);

        $disk = Storage::disk($item['disk']);
        abort_unless($disk->exists($item['path']), 404);

        $stream = $disk->readStream($item['path']);
        abort_if($stream === null, 404);

        $mime = $disk->mimeType($item['path']) ?: 'application/octet-stream';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    protected function otorisasi(mixed $user): void
    {
        abort_unless(
            $user instanceof User && $user->canAccessPanel(Filament::getPanel('akreditasi')),
            403,
        );
    }
}
