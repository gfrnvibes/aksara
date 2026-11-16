<?php

namespace App\Http\Controllers;

use App\Models\ProgramTahunan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CetakProtaController extends Controller
{
    public function __invoke(Request $request, ProgramTahunan $prota): View|Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        // Teacher can view their own PROTA, or admin/curriculum managers can view any
        $isOwner = $user->gtk !== null && $user->gtk->id === $prota->gtk_id;
        $isAdmin = $user->hasRole(config('filament-shield.super_admin.name', 'super_admin')) || $user->roles()->exists();
        abort_unless($isOwner || $isAdmin, 403);

        $prota->load(['tahunAjaran', 'kelas', 'mataPelajaran', 'gtk', 'items']);

        $sebagaiPdf = $request->query('format') === 'pdf';

        $data = [
            'prota' => $prota,
            'itemsGanjil' => $prota->items->where('semester', 1)->values(),
            'itemsGenap' => $prota->items->where('semester', 2)->values(),
            'totalJpGanjil' => $prota->totalJpSemester(1),
            'totalMingguGanjil' => $prota->totalMingguSemester(1),
            'totalJpGenap' => $prota->totalJpSemester(2),
            'totalMingguGenap' => $prota->totalMingguSemester(2),
            'totalJp' => $prota->totalJp(),
            'totalMinggu' => $prota->totalMingguEfektif(),
            'untukPdf' => $sebagaiPdf,
        ];

        if (! $sebagaiPdf) {
            return view('cetak.prota', $data);
        }

        $namaFile = Str::slug("PROTA {$prota->mataPelajaran->nama} {$prota->kelas->nama} {$prota->tahunAjaran->nama}").'.pdf';

        return response(
            Pdf::loadView('cetak.prota', $data)
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
