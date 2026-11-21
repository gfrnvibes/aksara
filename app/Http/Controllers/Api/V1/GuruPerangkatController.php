<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AlurTujuanPembelajaran;
use App\Models\AnggotaOrganisasi;
use App\Models\Ekstrakurikuler;
use App\Models\KegiatanOrganisasi;
use App\Models\PembagianMengajar;
use App\Models\PerangkatAjar;
use App\Models\ProgramSemester;
use App\Models\ProgramTahunan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\RingkasanTugasGuru;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruPerangkatController extends Controller
{
    public function index(Request $request, RingkasanTugasGuru $ringkasan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);
        Filament::setCurrentPanel('guru');

        $gtk = $user->gtk;
        $tahunAktif = TahunAjaran::query()->where('is_aktif', true)->first();

        $penugasan = PembagianMengajar::query()
            ->with(['kelas', 'mataPelajaran'])
            ->milik($gtk, $tahunAktif?->id)
            ->get()
            ->map(fn (PembagianMengajar $p): array => [
                'id' => $p->id,
                'kelas' => $p->kelas->nama,
                'mapel' => $p->mataPelajaran->nama,
                'singkatan' => $p->mataPelajaran->singkatan,
            ])
            ->values()
            ->all();

        return response()->json([
            'tahun_ajaran' => $tahunAktif?->nama,
            'ringkasan' => $ringkasan->untuk($user),
            'penugasan' => $penugasan,
            'dokumen' => [
                'atp' => AlurTujuanPembelajaran::query()->where('gtk_id', $gtk->id)->count(),
                'prota' => ProgramTahunan::query()->where('gtk_id', $gtk->id)->count(),
                'prosem' => ProgramSemester::query()->where('gtk_id', $gtk->id)->count(),
                'perangkat_ajar' => PerangkatAjar::query()->where('gtk_id', $gtk->id)->count(),
            ],
        ]);
    }

    public function organisasiIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);
        Filament::setCurrentPanel('guru');

        $gtk = $user->gtk;
        $tahunAktif = TahunAjaran::query()->where('is_aktif', true)->first();

        $ekskul = $gtk->ekskulBinaan()
            ->aktif()
            ->orderBy('nama')
            ->get()
            ->map(function (Ekstrakurikuler $item) use ($tahunAktif): array {
                $kegiatan = KegiatanOrganisasi::query()
                    ->where('ekstrakurikuler_id', $item->id)
                    ->when($tahunAktif !== null, fn ($q) => $q->where('tahun_ajaran_id', $tahunAktif->id))
                    ->orderByDesc('tanggal')
                    ->limit(15)
                    ->get()
                    ->map(fn (KegiatanOrganisasi $k): array => [
                        'id' => $k->id,
                        'tanggal' => $k->tanggal->format('Y-m-d'),
                        'materi' => $k->materi,
                        'jumlah_hadir' => $k->jumlah_hadir,
                        'catatan' => $k->catatan,
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'anggota_count' => AnggotaOrganisasi::query()
                        ->where('ekstrakurikuler_id', $item->id)
                        ->when($tahunAktif !== null, fn ($q) => $q->where('tahun_ajaran_id', $tahunAktif->id))
                        ->count(),
                    'kegiatan' => $kegiatan,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'tanggal' => AksesHarian::hariIni(),
            'ekskul' => $ekskul,
        ]);
    }

    public function organisasiKegiatanStore(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);
        Filament::setCurrentPanel('guru');

        $data = $request->validate([
            'ekstrakurikuler_id' => ['required', 'integer', 'exists:ekstrakurikulers,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'materi' => ['required', 'string', 'max:500'],
            'jumlah_hadir' => ['required', 'integer', 'min:0', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($user->gtk->ekskulBinaan()->whereKey((int) $data['ekstrakurikuler_id'])->exists(), 403);
        $tahunAktif = TahunAjaran::query()->where('is_aktif', true)->firstOrFail();

        KegiatanOrganisasi::query()->create([
            'ekstrakurikuler_id' => (int) $data['ekstrakurikuler_id'],
            'tahun_ajaran_id' => $tahunAktif->id,
            'pembina_id' => $user->gtk->id,
            'tanggal' => $data['tanggal'],
            'materi' => $data['materi'],
            'jumlah_hadir' => (int) $data['jumlah_hadir'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        return response()->json(['message' => 'Kegiatan ekstrakurikuler tersimpan.']);
    }
}
