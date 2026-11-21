<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gtk;
use App\Models\JurnalMengajar;
use App\Models\User;
use App\Services\Gemini\KlienGemini;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PenyusunJurnalGemini;
use App\Services\Harian\PertemuanMengajar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GuruJurnalController extends Controller
{
    public function index(Request $request, PertemuanMengajar $pertemuan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $tanggal = (string) ($data['tanggal'] ?? AksesHarian::hariIni());
        $piket = AksesHarian::piket($user, $tanggal);
        $daftar = $pertemuan->daftar($user, $tanggal, true)->values()->all();

        $guruOpsi = $piket
            ? Gtk::query()->aktif()->orderBy('nama')->get(['id', 'nama'])->map(fn (Gtk $gtk): array => [
                'id' => $gtk->id,
                'nama' => $gtk->nama,
            ])->values()->all()
            : [];

        return response()->json([
            'tanggal' => $tanggal,
            'piket' => $piket,
            'gemini_aktif' => KlienGemini::aktif(),
            'status_opsi' => $piket
                ? array_diff_key(JurnalMengajar::STATUS, ['hadir' => true])
                : ['terlaksana' => 'Terlaksana', 'kegiatan_pengganti' => 'Kegiatan pengganti'],
            'guru_opsi' => $guruOpsi,
            'pertemuan' => $daftar,
        ]);
    }

    public function rapikan(Request $request, PertemuanMengajar $pertemuan, PenyusunJurnalGemini $penyusun): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        if (! KlienGemini::aktif()) {
            return response()->json([
                'message' => 'Fitur Gemini AI belum aktif di server.',
            ], 422);
        }

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'sumber_jadwal' => ['required', 'string'],
            'catatan_asli' => ['required', 'string', 'max:3000'],
        ]);

        $item = $pertemuan->daftar($user, (string) $data['tanggal'], true)->get((string) $data['sumber_jadwal']);
        abort_if($item === null, 403);

        try {
            $result = $penyusun->susun(
                $user->id,
                (int) $item['kelas_id'],
                (string) $item['nama_mapel'],
                (string) $data['catatan_asli'],
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => KlienGemini::alasanGagal($e),
            ], 422);
        }

        return response()->json([
            'draf' => $result,
        ]);
    }

    public function hadir(Request $request, PertemuanMengajar $pertemuan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'sumber_jadwal' => ['required', 'string'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $jurnal = $pertemuan->simpan(
            $user,
            (string) $data['tanggal'],
            (string) $data['sumber_jadwal'],
            ['alasan' => $data['alasan'] ?? null],
            true,
        );

        return response()->json([
            'message' => 'Kehadiran tercatat. Lengkapi jurnal saat selesai mengajar.',
            'jurnal' => $jurnal->only(['id', 'sumber_jadwal', 'status', 'tanggal']),
        ]);
    }

    public function store(Request $request, PertemuanMengajar $pertemuan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'sumber_jadwal' => ['required', 'string'],
            'status' => ['required', 'string'],
            'materi' => ['nullable', 'string', 'max:3000'],
            'catatan_asli' => ['nullable', 'string', 'max:3000'],
            'kegiatan' => ['nullable', 'string', 'max:5000'],
            'refleksi' => ['nullable', 'string', 'max:3000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:3000'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'pelaksana_id' => ['nullable', 'integer', 'exists:gtks,id'],
            'dibantu_ai' => ['nullable', 'boolean'],
        ]);

        $jurnal = $pertemuan->simpan(
            $user,
            (string) $data['tanggal'],
            (string) $data['sumber_jadwal'],
            $data,
        );

        return response()->json([
            'message' => 'Jurnal tersimpan.',
            'jurnal' => $jurnal->only(['id', 'sumber_jadwal', 'status', 'materi', 'kegiatan', 'refleksi', 'tindak_lanjut', 'tanggal']),
        ]);
    }
}
