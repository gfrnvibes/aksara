<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Hari;
use App\Filament\Guru\Pages\JadwalSaya;
use App\Http\Controllers\Controller;
use App\Models\IsiJadwal;
use App\Models\KalenderAkademik;
use App\Models\PengaturanPresensi;
use App\Models\PresensiGtk;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\LokasiPresensi;
use App\Services\Harian\RingkasanTugasGuru;
use App\Services\Jadwal\PolaJam;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GuruDashboardController extends Controller
{
    public function index(Request $request, RingkasanTugasGuru $ringkasan, LokasiPresensi $lokasiPresensi): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);
        Filament::setCurrentPanel('guru');

        $gtk = $user->gtk;
        $hariIniStr = AksesHarian::hariIni();
        $jadwal = JadwalSaya::jadwalTerbit();
        $perHari = JadwalSaya::pelajaranPerHari($jadwal, $gtk);
        $hariIni = Hari::dariTanggal(now());
        $pola = PolaJam::muat();

        $pelajaranHariIni = $hariIni === null
            ? []
            : $perHari->get($hariIni->value, collect())->map(function (IsiJadwal $isi) use ($hariIni, $pola): array {
                $jam = $pola->jamUntuk($hariIni, $isi->jp_ke);

                return [
                    'id' => $isi->id,
                    'jp_ke' => $isi->jp_ke,
                    'waktu' => $jam?->rentang(),
                    'kelas' => $isi->kelas->nama,
                    'mapel' => $isi->pembagianMengajar->mataPelajaran->nama,
                    'singkatan' => $isi->pembagianMengajar->mataPelajaran->singkatan,
                ];
            })->values()->all();

        $presensiGtk = PresensiGtk::query()
            ->where('gtk_id', $gtk->id)
            ->where('tanggal', $hariIniStr)
            ->first();

        $kelasPerwalian = $gtk->kelasPerwalian()->aktif()->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama'])
            ->map(fn ($k): array => ['id' => $k->id, 'nama' => $k->nama])->values()->all();

        $ekskulBinaan = $gtk->ekskulBinaan()->aktif()->orderBy('nama')->get(['id', 'nama'])
            ->map(fn ($e): array => ['id' => $e->id, 'nama' => $e->nama])->values()->all();

        $agenda = KalenderAkademik::query()
            ->mendatang(30)
            ->limit(10)
            ->get()
            ->map(fn (KalenderAkademik $item): array => [
                'id' => $item->id,
                'nama' => $item->nama,
                'rentang' => $item->rentang(),
                'jenis' => $item->jenis->getLabel(),
                'keterangan' => $item->keterangan,
            ])
            ->values()
            ->all();

        return response()->json([
            'tanggal' => $hariIniStr,
            'hari_ini' => $hariIni?->getLabel() ?? 'Ahad',
            'peran' => [
                'is_wali_kelas' => count($kelasPerwalian) > 0,
                'is_piket' => AksesHarian::piket($user, $hariIniStr),
                'is_pembina_ekskul' => count($ekskulBinaan) > 0,
                'kelas_perwalian' => $kelasPerwalian,
                'ekskul_binaan' => $ekskulBinaan,
            ],
            'kehadiran_gtk_hari_ini' => $presensiGtk === null ? null : [
                'status' => $presensiGtk->status,
                'label' => PresensiGtk::STATUS[$presensiGtk->status] ?? $presensiGtk->status,
                'dicatat_pada' => filled($presensiGtk->dicatat_pada) ? Carbon::parse($presensiGtk->dicatat_pada)->timezone('Asia/Jakarta')->format('H:i') : null,
                'jarak_meter' => $presensiGtk->jarak_meter,
                'punya_foto' => filled($presensiGtk->foto_path),
                'foto_url' => filled($presensiGtk->foto_path) ? url("/api/v1/guru/kehadiran-gtk/{$presensiGtk->id}/foto") : null,
            ],
            'pengaturan_presensi' => $lokasiPresensi->ringkasanPengaturan(PengaturanPresensi::aktif()),
            'tugas' => $ringkasan->untuk($user),
            'jadwal_hari_ini' => $pelajaranHariIni,
            'agenda' => $agenda,
        ]);
    }
}
