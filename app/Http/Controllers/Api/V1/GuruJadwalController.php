<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Hari;
use App\Filament\Guru\Pages\JadwalSaya;
use App\Http\Controllers\Controller;
use App\Models\IsiJadwal;
use App\Models\User;
use App\Services\Jadwal\PolaJam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruJadwalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $jadwal = JadwalSaya::jadwalTerbit();
        $perHari = JadwalSaya::pelajaranPerHari($jadwal, $user->gtk);
        $pola = PolaJam::muat();
        $hariIni = Hari::dariTanggal(now('Asia/Jakarta'));

        $daftarHari = collect(Hari::cases())->map(function (Hari $hari) use ($perHari, $pola): array {
            $pelajaran = $perHari->get($hari->value, collect())->map(function (IsiJadwal $isi) use ($hari, $pola): array {
                $jam = $pola->jamUntuk($hari, $isi->jp_ke);

                return [
                    'id' => $isi->id,
                    'jp_ke' => $isi->jp_ke,
                    'waktu' => $jam?->rentang(),
                    'mulai' => $jam?->mulai,
                    'selesai' => $jam?->selesai,
                    'kelas' => $isi->kelas->nama,
                    'mapel' => $isi->pembagianMengajar->mataPelajaran->nama,
                    'singkatan' => $isi->pembagianMengajar->mataPelajaran->singkatan,
                ];
            })->values()->all();

            return [
                'hari' => $hari->value,
                'label' => $hari->getLabel(),
                'pelajaran' => $pelajaran,
            ];
        })->values()->all();

        return response()->json([
            'jadwal_terbit' => $jadwal !== null,
            'hari_ini' => $hariIni === null ? null : [
                'hari' => $hariIni->value,
                'label' => $hariIni->getLabel(),
            ],
            'hari' => $daftarHari,
        ]);
    }
}
