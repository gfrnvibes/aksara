<?php

namespace App\Http\Controllers;

use App\Models\CatatanPiket;
use App\Models\Gtk;
use App\Models\JurnalMengajar;
use App\Models\Kelas;
use App\Models\PenugasanPiket;
use App\Models\PresensiGtk;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CetakHarianController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->gtk?->is_aktif || AksesHarian::pengelola($user)), 403);
        $input = $request->validate([
            'jenis' => ['required', Rule::in(['siswa', 'gtk', 'jurnal', 'piket'])],
            'mulai' => ['required', 'date_format:Y-m-d'], 'selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:mulai'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'], 'gtk_id' => ['nullable', 'integer', 'exists:gtks,id'],
        ]);
        $mulai = Carbon::parse($input['mulai']);
        $selesai = Carbon::parse($input['selesai']);
        if ($mulai->diffInDays($selesai) > 366 || ($input['jenis'] === 'siswa' && $mulai->format('Y-m') !== $selesai->format('Y-m'))) {
            throw ValidationException::withMessages(['selesai' => 'Rekap siswa maksimal satu bulan kalender; rekap lainnya maksimal satu tahun.']);
        }
        $pengelola = AksesHarian::pengelola($user);
        $kelas = null;
        $gtk = null;
        $siswa = collect();
        $hari = [];
        $records = collect();
        if ($input['jenis'] === 'siswa') {
            $kelas = Kelas::query()->findOrFail((int) ($input['kelas_id'] ?? 0));
            abort_unless($pengelola || $kelas->wali_kelas_id === $user->gtk?->id, 403);
            $tahun = AksesHarian::tahun($input['mulai']);
            $records = PresensiSiswa::query()->where('kelas_id', $kelas->id)->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])->get();
            $siswa = Siswa::withTrashed()->where(fn ($q) => $q->anggotaKelas($kelas->id, $tahun->id)->orWhereIn('id', $records->pluck('siswa_id')))->orderBy('nama_lengkap')->get();
            for ($date = $mulai->copy(); $date->lte($selesai); $date->addDay()) {
                $hari[] = $date->toDateString();
            }
        } elseif (in_array($input['jenis'], ['gtk', 'jurnal'])) {
            $gtkId = $pengelola ? ($input['gtk_id'] ?? null) : $user->gtk?->id;
            abort_if(! $pengelola && filled($input['gtk_id'] ?? null) && (int) $input['gtk_id'] !== $gtkId, 403);
            $gtk = $gtkId ? Gtk::query()->findOrFail($gtkId) : null;
            $query = $input['jenis'] === 'gtk' ? PresensiGtk::query()->with(['gtk', 'pencatat']) : JurnalMengajar::query()->with(['pelaksana', 'pencatat']);
            $records = $query->when($gtkId, fn ($q) => $q->where($input['jenis'] === 'gtk' ? 'gtk_id' : 'guru_id', $gtkId))
                ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])->orderBy('tanggal')->get();
        } else {
            $records = CatatanPiket::query()->with(['kelas', 'siswa', 'gtk', 'pencatat'])
                ->when(! $pengelola, fn ($q) => $q->where(fn ($q) => $q->where('pencatat_id', $user->id)->orWhereIn('tanggal', PenugasanPiket::query()->where('gtk_id', $user->gtk->id ?? 0)->select('tanggal'))))
                ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])->orderBy('tanggal')->get();
        }
        $data = compact('input', 'mulai', 'selesai', 'kelas', 'gtk', 'siswa', 'hari', 'records');
        $data['untukPdf'] = $request->query('format') === 'pdf';
        if (! $data['untukPdf']) {
            return view('cetak.administrasi-harian', $data);
        }

        return response(Pdf::loadView('cetak.administrasi-harian', $data)->setPaper('a4', 'landscape')->output(), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="rekap-'.$input['jenis'].'-'.$input['mulai'].'.pdf"',
        ]);
    }
}
