<?php

namespace App\Http\Controllers;

use App\Models\AlurTujuanPembelajaran;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CetakAtpController extends Controller
{
    public function __invoke(Request $request, AlurTujuanPembelajaran $atp): View|Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $isOwner = $user->gtk !== null && $user->gtk->id === $atp->gtk_id;
        abort_unless($isOwner || $user->bolehLihatPerangkatAjarLain(), 403);

        $atp->load(['tahunAjaran', 'kelas', 'mataPelajaran', 'gtk', 'items']);

        $sebagaiPdf = $request->query('format') === 'pdf';

        $data = [
            'atp' => $atp,
            'items' => $atp->items,
            'totalJp' => $atp->totalJp(),
            'jumlahTp' => $atp->jumlahTp(),
            'untukPdf' => $sebagaiPdf,
        ];

        if (! $sebagaiPdf) {
            return view('cetak.atp', $data);
        }

        $namaFile = Str::slug("ATP {$atp->mataPelajaran->nama} {$atp->kelas->nama} {$atp->tahunAjaran->nama}").'.pdf';

        return response(
            Pdf::loadView('cetak.atp', $data)
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
