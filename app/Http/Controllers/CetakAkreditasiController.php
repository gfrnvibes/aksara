<?php

namespace App\Http\Controllers;

use App\Models\AkreditasiDokumen;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\PembuatBundelAkreditasi;
use App\Services\Surat\PengaturanKop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CetakAkreditasiController extends Controller
{
    public function dokumen(Request $request, AkreditasiDokumen $dokumen): View|Response
    {
        Gate::authorize('view', $dokumen);

        $dokumen->load(['tahunAjaran', 'kepalaMadrasah', 'ketuaTim']);
        $kop = PengaturanKop::untuk('surat_keterangan');

        if ($request->query('format') !== 'pdf') {
            return view('cetak.akreditasi-dokumen', [
                'dokumen' => $dokumen,
                'kop' => $kop,
                'untukPdf' => false,
            ]);
        }

        return Pdf::loadView('cetak.akreditasi-dokumen', [
            'dokumen' => $dokumen,
            'kop' => $kop,
            'untukPdf' => true,
        ])
            ->setPaper('a4', 'portrait')
            ->download(Str::slug($dokumen->kode.'-'.$dokumen->judul).'.pdf');
    }

    public function bundel(TahunAjaran $tahunAjaran, PembuatBundelAkreditasi $pembuat): BinaryFileResponse
    {
        Gate::authorize('viewAny', AkreditasiDokumen::class);

        $zipPath = $pembuat->buatZip($tahunAjaran);
        $nama = 'Bundel_Akreditasi_BANPDM_'.Str::slug($tahunAjaran->nama, '_').'.zip';

        return response()->download($zipPath, $nama)->deleteFileAfterSend(true);
    }
}
