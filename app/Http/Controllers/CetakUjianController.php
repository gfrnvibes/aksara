<?php

namespace App\Http\Controllers;

use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\Ujian;
use App\Services\Cbt\NaskahUjian;
use Illuminate\View\View;

/**
 * Prints an exam packet, its answer key or its blueprint as a browser page. DomPDF cannot join Arabic letters,
 * so exam papers are printed from the browser instead.
 */
class CetakUjianController extends Controller
{
    public function __invoke(Ujian $ujian, string $dokumen, ?string $paket = null): View
    {
        abort_unless(UjianResource::can('view', $ujian), 403);

        $daftarPaket = $ujian->daftarPaket();
        $paket ??= 'A';
        abort_unless(in_array($paket, $daftarPaket, true), 404);

        $ujian->load(['mataPelajaran', 'tahunAjaran']);

        return view('cetak.ujian', [
            'ujian' => $ujian,
            'dokumen' => $dokumen,
            'paket' => $paket,
            // The key lists every packet; the paper and the blueprint need only one.
            'naskah' => collect($dokumen === 'kunci' ? $daftarPaket : [$paket])
                ->mapWithKeys(fn (string $p): array => [$p => NaskahUjian::susun($ujian, $p)])
                ->all(),
        ]);
    }
}
