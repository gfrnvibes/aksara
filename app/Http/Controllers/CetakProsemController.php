<?php

namespace App\Http\Controllers;

use App\Models\ProgramSemester;
use App\Models\User;
use App\Services\Kurikulum\PenyusunProsemKurikulumMerdeka;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CetakProsemController extends Controller
{
    public function __invoke(Request $request, ProgramSemester $prosem): View|Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $isOwner = $user->gtk !== null && $user->gtk->id === $prosem->gtk_id;
        abort_unless($isOwner || $user->bolehLihatPerangkatAjarLain(), 403);

        $prosem->load(['tahunAjaran', 'kelas', 'mataPelajaran', 'gtk', 'items']);

        $sebagaiPdf = $request->query('format') === 'pdf';
        $daftarBulan = PenyusunProsemKurikulumMerdeka::daftarBulan($prosem->semester);

        $data = [
            'prosem' => $prosem,
            'daftarBulan' => $daftarBulan,
            'items' => $prosem->items,
            'totalJp' => $prosem->totalJp(),
            'untukPdf' => $sebagaiPdf,
        ];

        if (! $sebagaiPdf) {
            return view('cetak.prosem', $data);
        }

        $semStr = $prosem->semester === 2 ? 'Genap' : 'Ganjil';
        $namaFile = Str::slug("PROSEM {$prosem->mataPelajaran->nama} {$prosem->kelas->nama} {$prosem->tahunAjaran->nama} Sem {$semStr}").'.pdf';

        return response(
            Pdf::loadView('cetak.prosem', $data)
                ->setPaper('a4', 'landscape')
                ->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
            ]
        );
    }
}
