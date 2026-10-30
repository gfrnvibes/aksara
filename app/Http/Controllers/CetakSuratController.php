<?php

namespace App\Http\Controllers;

use App\Exports\SuratExport;
use App\Models\MutasiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\SuratKeluar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Prints or downloads the letters built by {@see SuratExport}. The letter number, purpose and date come from the
 * query string, filled in by the print action.
 */
class CetakSuratController extends Controller
{
    public function keteranganAktif(Request $request, Siswa $siswa): View|Response
    {
        Gate::authorize('view', $siswa);

        return $this->tampilkan($request, 'keterangan_aktif', SuratExport::keteranganAktif($siswa, $this->isian($request)));
    }

    public function pindah(Request $request, MutasiSiswa $mutasi): View|Response
    {
        Gate::authorize('view', $mutasi);

        return $this->tampilkan($request, 'pindah', SuratExport::pindah($mutasi, $this->isian($request)));
    }

    public function keluar(Request $request, SuratKeluar $surat): View|Response
    {
        Gate::authorize('view', $surat);

        return $this->tampilkan($request, 'keluar', SuratExport::keluar($surat));
    }

    public function transkrip(Request $request, Siswa $siswa): View|Response
    {
        Gate::authorize('view', $siswa);
        Gate::authorize('viewAny', Rapor::class);

        return $this->tampilkan($request, 'transkrip', SuratExport::transkrip($siswa, $this->isian($request)));
    }

    /**
     * @return array{nomor: string|null, keperluan: string|null, tanggal: string|null}
     */
    protected function isian(Request $request): array
    {
        $isian = $request->validate([
            'nomor' => ['nullable', 'string', 'max:255'],
            'keperluan' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date'],
        ]);

        return ['nomor' => $isian['nomor'] ?? null, 'keperluan' => $isian['keperluan'] ?? null, 'tanggal' => $isian['tanggal'] ?? null];
    }

    /**
     * @param  array{view: view-string, data: array<string, mixed>, namaFile: string}  $surat
     */
    protected function tampilkan(Request $request, string $jenis, array $surat): View|Response
    {
        activity()
            ->event('surat_dicetak')
            ->withProperties(['jenis' => $jenis, 'nomor' => $surat['data']['nomorSurat'] ?? null])
            ->log('Surat dicetak');

        if ($request->query('format') !== 'pdf') {
            return view($surat['view'], [...$surat['data'], 'untukPdf' => false]);
        }

        return response(SuratExport::pdf($surat), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$surat['namaFile'].'"',
        ]);
    }
}
