<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Hari;
use App\Enums\JenisPermintaanJadwal;
use App\Enums\SifatPermintaanJadwal;
use App\Http\Controllers\Controller;
use App\Models\CatatanPiket;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PengaturanPresensi;
use App\Models\PenugasanPiket;
use App\Models\PermintaanJadwal;
use App\Models\PresensiGtk;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\LokasiPresensi;
use App\Services\Harian\PresensiHarian;
use App\Services\Harian\WatermarkFotoPresensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuruHarianController extends Controller
{
    public function kehadiranGtkIndex(Request $request, LokasiPresensi $lokasiPresensi): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $tanggal = AksesHarian::hariIni();
        $isPiket = AksesHarian::pengelola($user)
            || PenugasanPiket::query()->where('gtk_id', $user->gtk->id)->exists();

        $riwayat = [];
        $rows = PresensiGtk::query()
            ->with(['gtk', 'pencatat'])
            ->when(! AksesHarian::pengelola($user), fn ($q) => $q->where(fn ($q) => $q
                ->where('gtk_id', $user->gtk->id)
                ->orWhereIn('tanggal', PenugasanPiket::query()->where('gtk_id', $user->gtk->id)->select('tanggal'))))
            ->orderByDesc('tanggal')
            ->limit(30)
            ->get();

        foreach ($rows as $item) {
            $riwayat[] = [
                'id' => $item->id,
                'tanggal' => $item->tanggal->format('Y-m-d'),
                'gtk_id' => $item->gtk_id,
                'gtk_nama' => $item->gtk->nama,
                'status' => $item->status,
                'status_label' => PresensiGtk::STATUS[$item->status] ?? $item->status,
                'catatan' => $item->catatan,
                'jarak_meter' => $item->jarak_meter,
                'punya_foto' => filled($item->foto_path),
                'foto_url' => filled($item->foto_path)
                    ? $request->getSchemeAndHttpHost().URL::temporarySignedRoute('api.guru.kehadiran-gtk.foto', now()->addMinutes(15), ['presensiGtk' => $item->id], absolute: false)
                    : null,
                'waktu' => filled($item->dicatat_pada) ? Carbon::parse($item->dicatat_pada)->timezone('Asia/Jakarta')->format('d M Y H:i') : null,
            ];
        }

        $gtkOpsi = $isPiket
            ? Gtk::query()->aktif()->orderBy('nama')->get(['id', 'nama'])->map(fn (Gtk $g): array => [
                'id' => $g->id,
                'nama' => $g->nama,
            ])->values()->all()
            : [];

        return response()->json([
            'tanggal' => $tanggal,
            'bisa_catat_piket' => AksesHarian::piket($user, $tanggal),
            'pengaturan_presensi' => $lokasiPresensi->ringkasanPengaturan(PengaturanPresensi::aktif()),
            'status_opsi' => PresensiGtk::STATUS,
            'gtk_opsi' => $gtkOpsi,
            'riwayat' => $riwayat,
        ]);
    }

    public function kehadiranGtkHadir(
        Request $request,
        PresensiHarian $presensi,
        LokasiPresensi $lokasiPresensi,
        WatermarkFotoPresensi $watermark,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $pengaturan = PengaturanPresensi::aktif();
        $wajibFoto = $pengaturan->aktif && $pengaturan->wajib_foto;

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'akurasi_meter' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'foto' => [Rule::requiredIf($wajibFoto), 'nullable', 'image', 'mimes:jpg,jpeg,webp', 'max:2048'],
        ]);

        $lokasiPresensi->validasiWaktu($pengaturan);
        $latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $jarak = $lokasiPresensi->validasiLokasi($pengaturan, $latitude, $longitude);

        $fotoPath = null;
        $foto = $request->file('foto');
        if ($foto instanceof UploadedFile) {
            $stored = $foto->store('presensi-gtk/'.now('Asia/Jakarta')->format('Y-m'), 'local');
            if ($stored !== false) {
                $fotoPath = $stored;
                $watermark->terapkan($fotoPath, [
                    'nama' => $user->gtk->nama,
                    'waktu' => now('Asia/Jakarta')->format('d M Y H:i:s').' WIB',
                    'lokasi' => $pengaturan->nama_lokasi,
                    'jarak' => $jarak,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
            }
        }

        $record = $presensi->gtk($user, $user->gtk, AksesHarian::hariIni(), 'hadir');
        $record->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'jarak_meter' => $jarak,
            'akurasi_meter' => isset($data['akurasi_meter']) ? (int) $data['akurasi_meter'] : null,
            'foto_path' => $fotoPath ?: $record->foto_path,
        ]);

        return response()->json([
            'message' => 'Kehadiran hari ini tercatat.',
            'status' => $record->status,
            'jarak_meter' => $record->jarak_meter,
            'foto_url' => filled($record->foto_path)
                ? $request->getSchemeAndHttpHost().URL::temporarySignedRoute('api.guru.kehadiran-gtk.foto', now()->addMinutes(15), ['presensiGtk' => $record->id], absolute: false)
                : null,
        ]);
    }

    public function kehadiranGtkFoto(Request $request, PresensiGtk $presensiGtk): StreamedResponse
    {
        if (! $request->hasValidSignature(false)) {
            $user = auth('sanctum')->user();
            abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 401);

            $isOwner = $presensiGtk->gtk_id === $user->gtk->id;
            $isPiket = AksesHarian::piket($user, $presensiGtk->tanggal->format('Y-m-d'));
            abort_unless($isOwner || $isPiket || AksesHarian::pengelola($user), 403);
        }

        $path = $presensiGtk->foto_path;
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function kehadiranGtkStore(Request $request, PresensiHarian $presensi): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'gtk_id' => ['required', 'integer', 'exists:gtks,id'],
            'status' => ['required', 'string'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless(AksesHarian::piket($user, (string) $data['tanggal']), 403);
        $target = Gtk::query()->findOrFail((int) $data['gtk_id']);
        $presensi->gtk($user, $target, (string) $data['tanggal'], (string) $data['status'], $data['catatan'] ?? null);

        return response()->json(['message' => 'Kehadiran GTK tersimpan.']);
    }

    public function piketIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $bisaPiket = AksesHarian::pengelola($user)
            || PenugasanPiket::query()->where('gtk_id', $user->gtk->id)->exists();
        abort_unless($bisaPiket, 403);

        $catatan = [];
        $catatanRows = CatatanPiket::query()
            ->with(['kelas', 'siswa', 'gtk', 'pencatat'])
            ->when(! AksesHarian::pengelola($user), fn ($q) => $q->where(fn ($q) => $q
                ->where('pencatat_id', $user->id)
                ->orWhereIn('tanggal', PenugasanPiket::query()->where('gtk_id', $user->gtk->id)->select('tanggal'))))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($catatanRows as $item) {
            $catatan[] = [
                'id' => $item->id,
                'tanggal' => $item->tanggal->format('Y-m-d'),
                'jenis' => $item->jenis,
                'jenis_label' => CatatanPiket::JENIS[$item->jenis] ?? $item->jenis,
                'kelas' => $item->kelas?->nama,
                'siswa' => $item->siswa?->nama_lengkap,
                'gtk' => $item->gtk?->nama,
                'kejadian' => $item->kejadian,
                'tindak_lanjut' => $item->tindak_lanjut,
                'terlambat_menit' => $item->terlambat_menit,
                'jam_pelajaran' => $item->jam_pelajaran,
            ];
        }

        $kelas = Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama'])
            ->map(fn (Kelas $k): array => ['id' => $k->id, 'nama' => $k->nama])->values()->all();

        $gtks = Gtk::query()->aktif()->orderBy('nama')->get(['id', 'nama'])
            ->map(fn (Gtk $g): array => ['id' => $g->id, 'nama' => $g->nama])->values()->all();

        return response()->json([
            'tanggal' => AksesHarian::hariIni(),
            'jenis_opsi' => CatatanPiket::JENIS,
            'kelas_opsi' => $kelas,
            'gtk_opsi' => $gtks,
            'catatan' => $catatan,
        ]);
    }

    public function piketSiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate(['kelas_id' => ['required', 'integer', 'exists:kelas,id']]);

        $siswa = Siswa::query()->aktif()->where('kelas_id', (int) $data['kelas_id'])
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap'])
            ->map(fn (Siswa $s): array => ['id' => $s->id, 'nama' => $s->nama_lengkap])->values()->all();

        return response()->json(['siswa' => $siswa]);
    }

    public function piketStore(Request $request, PresensiHarian $presensi): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jenis' => ['required', 'string'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'siswa_id' => ['nullable', 'integer', 'exists:siswas,id'],
            'gtk_id' => ['nullable', 'integer', 'exists:gtks,id'],
            'terlambat_menit' => ['nullable', 'integer', 'min:0', 'max:600'],
            'jam_pelajaran' => ['nullable', 'string', 'max:100'],
            'kejadian' => ['required', 'string', 'max:3000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:3000'],
        ]);

        $presensi->piket($user, (string) $data['tanggal'], $data);

        return response()->json(['message' => 'Kejadian piket tercatat.']);
    }

    public function permintaanJadwalIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $items = PermintaanJadwal::query()
            ->with('mataPelajaran')
            ->where('gtk_id', $user->gtk->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PermintaanJadwal $item): array => [
                'id' => $item->id,
                'jenis' => $item->jenis->value,
                'jenis_label' => $item->jenis->getLabel(),
                'sifat' => $item->sifat->value,
                'sifat_label' => $item->sifat->getLabel(),
                'hari' => $item->hari ?? [],
                'jp_mulai' => $item->jp_mulai,
                'jp_selesai' => $item->jp_selesai,
                'mapel' => $item->mataPelajaran?->nama,
                'keterangan' => $item->keterangan,
                'is_aktif' => $item->is_aktif,
            ])
            ->values()
            ->all();

        return response()->json([
            'jenis_opsi' => collect(JenisPermintaanJadwal::cases())->mapWithKeys(fn ($c): array => [$c->value => $c->getLabel()])->all(),
            'sifat_opsi' => collect(SifatPermintaanJadwal::cases())->mapWithKeys(fn ($c): array => [$c->value => $c->getLabel()])->all(),
            'hari_opsi' => collect(Hari::cases())->mapWithKeys(fn ($c): array => [$c->value => $c->getLabel()])->all(),
            'mapel_opsi' => MataPelajaran::query()->orderBy('nama')->pluck('nama', 'id')->all(),
            'items' => $items,
        ]);
    }

    public function permintaanJadwalStore(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'jenis' => ['required', Rule::enum(JenisPermintaanJadwal::class)],
            'sifat' => ['required', Rule::enum(SifatPermintaanJadwal::class)],
            'hari' => ['nullable', 'array'],
            'hari.*' => ['integer', 'between:1,6'],
            'jp_mulai' => ['nullable', 'integer', 'min:1', 'max:15'],
            'jp_selesai' => ['nullable', 'integer', 'min:1', 'max:15'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajarans,id'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        PermintaanJadwal::query()->create([
            'gtk_id' => $user->gtk->id,
            'jenis' => $data['jenis'],
            'sifat' => $data['sifat'],
            'hari' => $data['hari'] ?? null,
            'jp_mulai' => $data['jp_mulai'] ?? null,
            'jp_selesai' => $data['jp_selesai'] ?? null,
            'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'is_aktif' => true,
        ]);

        return response()->json(['message' => 'Permintaan jadwal disimpan.']);
    }

    public function permintaanJadwalDestroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $item = PermintaanJadwal::query()->where('gtk_id', $user->gtk->id)->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Permintaan jadwal dihapus.']);
    }
}
