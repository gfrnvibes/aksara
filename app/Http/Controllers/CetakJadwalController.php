<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\PenandaTangan;
use App\Services\Jadwal\TampilanJadwal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Prints a timetable on one landscape page: the grid of the days, the legend of codes, and the signatures of the
 * principal and the vice principal for curriculum.
 */
class CetakJadwalController extends Controller
{
    public function __invoke(Request $request, Jadwal $jadwal): View|Response
    {
        Gate::authorize('view', $jadwal);

        $sebagaiPdf = $request->query('format') === 'pdf';
        $data = [
            'tampilan' => TampilanJadwal::untuk($jadwal->load('tahunAjaran')),
            'wakilKurikulum' => PenandaTangan::query()
                ->aktifBerurutan()
                ->whereRaw('lower(jabatan) like ?', ['%kurikulum%'])
                ->first(),
            'untukPdf' => $sebagaiPdf,
        ];

        if (! $sebagaiPdf) {
            return view('cetak.jadwal', $data);
        }

        return response(Pdf::loadView('cetak.jadwal', $data)->setPaper('a4', 'landscape')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="jadwal-'.Str::slug($jadwal->judul()).'.pdf"',
        ]);
    }
}
