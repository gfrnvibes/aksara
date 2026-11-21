<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PresensiHarian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruPresensiController extends Controller
{
    public function opsi(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $tanggal = (string) ($data['tanggal'] ?? AksesHarian::hariIni());
        AksesHarian::tanggal($tanggal, true);
        $piket = AksesHarian::piket($user, $tanggal);

        $kelas = Kelas::query()
            ->aktif()
            ->when(! $piket, fn ($q) => $q->where('wali_kelas_id', $user->gtk->id))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tingkat'])
            ->map(fn (Kelas $item): array => [
                'id' => $item->id,
                'nama' => $item->nama,
                'tingkat' => $item->tingkat,
            ])
            ->values()
            ->all();

        return response()->json([
            'tanggal' => $tanggal,
            'piket' => $piket,
            'status_opsi' => PresensiSiswa::STATUS,
            'kelas' => $kelas,
        ]);
    }

    public function index(Request $request, PresensiHarian $presensi): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $tanggal = (string) ($data['tanggal'] ?? AksesHarian::hariIni());
        $kelas = Kelas::query()->findOrFail((int) $data['kelas_id']);
        abort_unless(AksesHarian::kelas($user, $kelas, $tanggal), 403);
        $tahun = AksesHarian::tahun($tanggal);

        $saved = PresensiSiswa::query()
            ->where('kelas_id', $kelas->id)
            ->where('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        $siswa = Siswa::query()
            ->anggotaKelas($kelas->id, $tahun->id)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function (Siswa $item) use ($saved): array {
                $catatan = $saved->get($item->id);

                return [
                    'siswa_id' => $item->id,
                    'nis' => $item->nis,
                    'nama' => $item->nama_lengkap,
                    'status' => $catatan?->status,
                    'terlambat_menit' => $catatan->terlambat_menit ?? 0,
                    'catatan' => $catatan?->catatan,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'tanggal' => $tanggal,
            'kelas' => [
                'id' => $kelas->id,
                'nama' => $kelas->nama,
            ],
            'versi' => $presensi->versi($kelas->id, $tanggal),
            'siswa' => $siswa,
        ]);
    }

    public function store(Request $request, PresensiHarian $presensi): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'versi' => ['required', 'string'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'baris' => ['required', 'array', 'min:1'],
        ]);

        $kelas = Kelas::query()->findOrFail((int) $data['kelas_id']);
        $presensi->simpanKelas(
            $user,
            $kelas,
            (string) $data['tanggal'],
            array_values($data['baris']),
            (string) $data['versi'],
            $data['alasan'] ?? null,
        );

        return response()->json([
            'message' => 'Presensi tersimpan dan rekap rapor yang belum dikunci diperbarui.',
            'versi' => $presensi->versi($kelas->id, (string) $data['tanggal']),
        ]);
    }
}
