<?php

namespace App\Http\Controllers;

use App\Models\PerangkatAjar;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CetakPerangkatAjarController extends Controller
{
    /**
     * Memeriksa otorisasi guru pengampu atau admin
     */
    protected function periksaAkses(Request $request, PerangkatAjar $perangkatAjar): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $isOwner = $user->gtk !== null && $user->gtk->id === $perangkatAjar->gtk_id;
        $isAdmin = $user->hasRole(config('filament-shield.super_admin.name', 'super_admin')) || $user->roles()->exists();

        abort_unless($isOwner || $isAdmin, 403);
    }

    /**
     * [🖨️ Cetak Perangkat Ini]: Mencetak hanya perangkat pada tab yang sedang aktif
     * (ame, atp, prota, prosem, atau modul berdasarkan kode_tp)
     */
    public function cetakSatuan(Request $request, PerangkatAjar $perangkatAjar, string $tab, ?string $kodeTp = null): View|Response
    {
        $this->periksaAkses($request, $perangkatAjar);

        $perangkatAjar->load(['tahunAjaran', 'kelas', 'mataPelajaran', 'gtk']);

        $tab = strtolower($tab);
        $sebagaiPdf = $request->query('format') === 'pdf';

        $modulTerpilih = null;
        if ($tab === 'modul') {
            if ($kodeTp && $kodeTp !== 'all') {
                $modulTerpilih = $perangkatAjar->getModulAjar($kodeTp);
            }
            if (! $modulTerpilih && ! empty($perangkatAjar->modul_ajars)) {
                $firstKey = array_key_first($perangkatAjar->modul_ajars);
                $modulTerpilih = $perangkatAjar->modul_ajars[$firstKey] ?? null;
                $kodeTp = (string) $firstKey;
            }
        }

        $data = [
            'perangkat' => $perangkatAjar,
            'tab' => $tab,
            'kodeTp' => $kodeTp,
            'modul' => $modulTerpilih,
            'untukPdf' => $sebagaiPdf,
        ];

        if (! $sebagaiPdf) {
            return view('cetak.perangkat-ajar.satuan', $data);
        }

        $orientasi = match ($tab) {
            'atp', 'prota', 'prosem' => 'landscape',
            default => 'portrait',
        };

        $judulDoc = match ($tab) {
            'ame' => 'AME',
            'atp' => 'ATP',
            'prota' => 'PROTA',
            'prosem' => 'PROSEM',
            'modul' => 'Modul Ajar TP '.$kodeTp,
            default => 'Perangkat Ajar',
        };

        $namaFile = Str::slug("{$judulDoc} {$perangkatAjar->mataPelajaran?->nama} {$perangkatAjar->kelas?->nama} {$perangkatAjar->tahunAjaran?->nama}").'.pdf';

        return response(
            Pdf::loadView('cetak.perangkat-ajar.satuan', $data)
                ->setPaper('a4', $orientasi)
                ->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
            ]
        );
    }

    /**
     * [📚 Cetak Paket Lengkap (Bundel)]:
     * Menggabungkan Halaman Sampul (Cover) + Lembar Pengesahan + AME + ATP + PROTA + PROSEM + seluruh Modul Ajar
     */
    public function cetakBundel(Request $request, PerangkatAjar $perangkatAjar): View|Response
    {
        $this->periksaAkses($request, $perangkatAjar);

        $perangkatAjar->load(['tahunAjaran', 'kelas', 'mataPelajaran', 'gtk']);

        $sebagaiPdf = $request->query('format') === 'pdf';

        $data = [
            'perangkat' => $perangkatAjar,
            'untukPdf' => $sebagaiPdf,
        ];

        if (! $sebagaiPdf) {
            return view('cetak.perangkat-ajar.bundel', $data);
        }

        $namaFile = Str::slug("BUNDEL Perangkat Ajar {$perangkatAjar->mataPelajaran?->nama} {$perangkatAjar->kelas?->nama} {$perangkatAjar->tahunAjaran?->nama}").'.pdf';

        return response(
            Pdf::loadView('cetak.perangkat-ajar.bundel', $data)
                ->setPaper('a4', 'portrait')
                ->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
            ]
        );
    }
}
