<?php

namespace App\Http\Controllers;

use App\Enums\Agama;
use App\Http\Requests\DaftarPpdbRequest;
use App\Models\Ppdb;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Arsip\SimpanBerkasArsip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * The public PPDB page: the registration form while registration is open, and a status check for applicants.
 */
class PpdbController extends Controller
{
    public function create(): View
    {
        return view('ppdb', [
            'tahunAjaran' => TahunAjaran::pendaftaranPpdbDibuka(),
            'pilihanAgama' => Agama::cases(),
        ]);
    }

    public function store(DaftarPpdbRequest $request, SimpanBerkasArsip $penyimpan): RedirectResponse
    {
        $tahunAjaran = TahunAjaran::pendaftaranPpdbDibuka();
        $berkas = [];

        try {
            foreach (array_keys(Ppdb::BERKAS) as $kunci) {
                $file = $request->file("berkas.{$kunci}");

                if ($file instanceof UploadedFile) {
                    $berkas[$kunci] = $penyimpan->simpan($file, 'ppdb', $kunci === 'foto' ? Siswa::SISI_FOTO : SimpanBerkasArsip::SISI_TERPANJANG);
                }
            }

            $ppdb = Ppdb::query()->create([
                ...$request->safe()->except('berkas'),
                'tahun_ajaran_id' => $tahunAjaran?->id,
                'berkas' => $berkas,
            ]);
        } catch (Throwable $exception) {
            Storage::disk(Ppdb::disk())->delete(array_values($berkas));

            throw $exception;
        }

        return to_route('ppdb')->with('terdaftar', ['nomor' => $ppdb->nomor, 'nama' => $ppdb->nama_lengkap]);
    }

    /**
     * The status of a registration, found by its number and the applicant's date of birth.
     */
    public function cekStatus(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('cekStatus', [
            'nomor' => ['required', 'string', 'max:30'],
            'tanggal_lahir_cek' => ['required', 'date'],
        ], [], ['nomor' => 'nomor pendaftaran', 'tanggal_lahir_cek' => 'tanggal lahir']);

        $ppdb = Ppdb::query()
            ->where('nomor', strtoupper(trim($data['nomor'])))
            ->whereDate('tanggal_lahir', $data['tanggal_lahir_cek'])
            ->first();

        if ($ppdb === null) {
            return redirect()->to(route('ppdb').'#cek-status')
                ->withErrors(['nomor' => 'Pendaftaran tidak ditemukan. Periksa kembali nomor pendaftaran dan tanggal lahir.'], 'cekStatus')
                ->withInput();
        }

        return redirect()->to(route('ppdb').'#cek-status')->with('hasilCek', [
            'nomor' => $ppdb->nomor,
            'nama' => $ppdb->nama_lengkap,
            'status' => $ppdb->status->getLabel(),
            'warna' => $ppdb->status->getColor(),
            'pesan' => $ppdb->status->pesanUntukPendaftar(),
        ]);
    }
}
