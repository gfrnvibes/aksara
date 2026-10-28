<?php

namespace App\Http\Controllers;

use App\Enums\KeputusanKenaikan;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Prints one semester's report card in the layout of the curriculum its academic year follows.
 */
class CetakRaporController extends Controller
{
    public function __invoke(Request $request, Rapor $rapor): View|Response
    {
        Gate::authorize('view', $rapor);

        $rapor->load(['siswa', 'kelas', 'tahunAjaran', 'nilaiRapor.mataPelajaran', 'nilaiEkstrakurikuler.ekstrakurikuler']);

        $sebagaiPdf = $request->query('format') === 'pdf';
        $data = [...$this->dataRapor($rapor), 'untukPdf' => $sebagaiPdf];

        if (! $sebagaiPdf) {
            return view('cetak.rapor', $data);
        }

        return response(Pdf::loadView('cetak.rapor', $data)->setPaper('a4')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->namaFile($rapor).'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataRapor(Rapor $rapor): array
    {
        $skema = $rapor->skema();

        $nilaiPerKelompok = $rapor->nilaiRapor
            ->sortBy(fn (NilaiRapor $nilai): array => [$nilai->mataPelajaran->kelompok->value, $nilai->mataPelajaran->urutan])
            ->groupBy(fn (NilaiRapor $nilai): string => $skema->labelKelompok($nilai->mataPelajaran->kelompok));

        return [
            'rapor' => $rapor,
            'skema' => $skema,
            'nilaiPerKelompok' => $nilaiPerKelompok,
            'jumlahNilai' => $rapor->nilaiRapor->sum(fn (NilaiRapor $nilai): int => array_sum($skema->nilaiUntukRataRata($nilai))),
            'fase' => $skema->fase($rapor->kelas->tingkat),
            'keputusan' => $this->keputusan($rapor),
            'waliKelas' => $rapor->penandaTanganWaliKelas(),
        ];
    }

    /**
     * The promotion sentence of an even-semester report card, e.g. "Naik ke kelas VIII".
     */
    protected function keputusan(Rapor $rapor): ?string
    {
        $tingkat = KelasResource::getTingkatOptions();

        return match ($rapor->keputusan) {
            KeputusanKenaikan::Naik => 'Naik ke kelas '.($tingkat[$rapor->kelas->tingkat + 1] ?? $rapor->kelas->tingkat + 1),
            KeputusanKenaikan::TinggalDiKelas => 'Tinggal di kelas '.($tingkat[$rapor->kelas->tingkat] ?? $rapor->kelas->tingkat),
            KeputusanKenaikan::Lulus => 'Lulus',
            null => null,
        };
    }

    protected function namaFile(Rapor $rapor): string
    {
        return Str::slug("rapor {$rapor->siswa->nis} {$rapor->siswa->nama_lengkap} {$rapor->tahunAjaran->nama} semester {$rapor->semester->name}").'.pdf';
    }
}
