<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\PembagianMengajar;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\CapaianKompetensi\PenyusunCapaianKompetensi;
use App\Services\Leger\SusunLeger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuruNilaiController extends Controller
{
    public function opsi(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $gtk = $user->gtk;
        $tahunAktif = TahunAjaran::query()->where('is_aktif', true)->first();

        $penugasan = PembagianMengajar::query()
            ->with(['tahunAjaran', 'kelas', 'mataPelajaran'])
            ->milik($gtk)
            ->get()
            ->map(fn (PembagianMengajar $p): array => [
                'tahun_ajaran_id' => $p->tahun_ajaran_id,
                'tahun_ajaran' => $p->tahunAjaran->nama,
                'kelas_id' => $p->kelas_id,
                'kelas' => $p->kelas->nama,
                'mata_pelajaran_id' => $p->mata_pelajaran_id,
                'mapel' => $p->mataPelajaran->nama,
                'kkm' => $p->mataPelajaran->kkm,
            ])
            ->values()
            ->all();

        $kelasPerwalian = $gtk->kelasPerwalian()->aktif()->orderBy('tingkat')->orderBy('nama')
            ->get(['id', 'nama'])->map(fn (Kelas $k): array => ['id' => $k->id, 'nama' => $k->nama])->values()->all();

        return response()->json([
            'tahun_ajaran_aktif_id' => $tahunAktif?->id,
            'semester_aktif' => Semester::berdasarkanTanggal(now())->value,
            'semester_opsi' => collect(Semester::cases())->mapWithKeys(fn (Semester $s): array => [$s->value => $s->getLabel()])->all(),
            'penugasan' => $penugasan,
            'kelas_perwalian' => $kelasPerwalian,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajarans,id'],
            'semester' => ['required', Rule::enum(Semester::class)],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajarans,id'],
        ]);

        $diizinkan = PembagianMengajar::query()
            ->milik($user->gtk, (int) $data['tahun_ajaran_id'])
            ->where('kelas_id', (int) $data['kelas_id'])
            ->where('mata_pelajaran_id', (int) $data['mata_pelajaran_id'])
            ->exists();
        abort_unless($diizinkan, 403);

        $kelas = Kelas::query()->findOrFail((int) $data['kelas_id']);
        $skema = TahunAjaran::skemaUntuk((int) $data['tahun_ajaran_id']);
        $semester = Semester::from((int) $data['semester']);

        $daftarSiswa = Siswa::query()
            ->anggotaKelas($kelas->id, (int) $data['tahun_ajaran_id'])
            ->orderBy('nama_lengkap')
            ->get();

        $daftarRapor = Rapor::query()
            ->withTrashed()
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', (int) $data['tahun_ajaran_id'])
            ->where('semester', $semester)
            ->with(['nilaiRapor' => fn ($q) => $q->where('mata_pelajaran_id', (int) $data['mata_pelajaran_id'])])
            ->get()
            ->keyBy('siswa_id');

        $kkmTersimpan = $daftarRapor->flatMap(fn (Rapor $r) => $r->nilaiRapor)->pluck('kkm')->first();
        $kkmDefault = $kkmTersimpan ?? MataPelajaran::query()->whereKey((int) $data['mata_pelajaran_id'])->value('kkm') ?? 75;

        $siswa = $daftarSiswa->map(function (Siswa $s) use ($daftarRapor): array {
            /** @var Rapor|null $rapor */
            $rapor = $daftarRapor->get($s->id);
            $nilai = $rapor?->nilaiRapor->first();

            return [
                'siswa_id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama_lengkap,
                'terkunci' => $rapor?->trashed() ?? false,
                'nilai_pengetahuan' => $nilai?->nilai_pengetahuan,
                'nilai_keterampilan' => $nilai?->nilai_keterampilan,
                'nilai_akhir' => $nilai?->nilai_akhir,
                'sikap' => $nilai?->sikap,
                'capaian_kompetensi' => $nilai?->capaian_kompetensi,
            ];
        })->values()->all();

        $kolomNilai = [];
        foreach ($skema->kolomNilai() as $kolom => $singkatan) {
            $kolomNilai[$kolom] = $skema->labelKolomNilai($kolom);
        }

        return response()->json([
            'kkm' => (int) $kkmDefault,
            'kolom_nilai' => $kolomNilai,
            'pakai_predikat' => $skema->pakaiPredikat(),
            'pakai_capaian' => $skema->pakaiCapaianKompetensi(),
            'predikat_sikap' => array_keys(RaporForm::PREDIKAT_SIKAP),
            'siswa' => $siswa,
        ]);
    }

    public function buatCapaian(Request $request, PenyusunCapaianKompetensi $penyusun): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajarans,id'],
            'semester' => ['required', Rule::enum(Semester::class)],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajarans,id'],
            'kkm' => ['required', 'integer', 'between:0,100'],
            'materi' => ['nullable', 'string', 'max:1000'],
            'timpa' => ['nullable', 'boolean'],
            'baris' => ['required', 'array', 'min:1'],
            'baris.*.siswa_id' => ['required', 'integer'],
            'baris.*.nilai_akhir' => ['nullable', 'integer', 'between:0,100'],
            'baris.*.capaian_kompetensi' => ['nullable', 'string'],
            'baris.*.terkunci' => ['nullable', 'boolean'],
        ]);

        $diizinkan = PembagianMengajar::query()
            ->milik($user->gtk, (int) $data['tahun_ajaran_id'])
            ->where('kelas_id', (int) $data['kelas_id'])
            ->where('mata_pelajaran_id', (int) $data['mata_pelajaran_id'])
            ->exists();
        abort_unless($diizinkan, 403);

        $skema = TahunAjaran::skemaUntuk((int) $data['tahun_ajaran_id']);
        if (! $skema->pakaiCapaianKompetensi()) {
            return response()->json([
                'message' => 'Skema kurikulum tahun ajaran ini tidak memakai capaian kompetensi.',
            ], 422);
        }

        $namaMapel = (string) MataPelajaran::query()->whereKey((int) $data['mata_pelajaran_id'])->value('nama');
        $kktp = (int) $data['kkm'];
        $timpa = (bool) ($data['timpa'] ?? false);
        $daftarNilai = [];

        foreach ($data['baris'] as $row) {
            if (($row['terkunci'] ?? false) || ! is_numeric($row['nilai_akhir'] ?? null)) {
                continue;
            }
            if (filled($row['capaian_kompetensi'] ?? null) && ! $timpa) {
                continue;
            }
            $daftarNilai[(int) $row['siswa_id']] = [
                'mata_pelajaran' => $namaMapel,
                'nilai' => (int) $row['nilai_akhir'],
                'kktp' => $kktp,
            ];
        }

        if ($daftarNilai === []) {
            return response()->json([
                'message' => 'Isi Nilai Akhir terlebih dahulu atau aktifkan ganti capaian yang sudah terisi.',
            ], 422);
        }

        $tingkat = Kelas::query()->whereKey((int) $data['kelas_id'])->value('tingkat');
        $semester = Semester::from((int) $data['semester']);
        $konteks = [
            'kelas' => $tingkat === null ? null : (KelasResource::getTingkatOptions()[$tingkat] ?? (string) $tingkat),
            'fase' => $tingkat === null ? null : $skema->fase((int) $tingkat),
            'semester' => $semester->getLabel(),
        ];

        $hasil = $penyusun->susun($daftarNilai, $konteks, $data['materi'] ?? null);

        return response()->json([
            'sumber' => $hasil['sumber'],
            'alasan_gagal_ai' => $hasil['alasanGagalAi'],
            'capaian' => $hasil['capaian'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajarans,id'],
            'semester' => ['required', Rule::enum(Semester::class)],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajarans,id'],
            'kkm' => ['required', 'integer', 'between:0,100'],
            'baris' => ['required', 'array', 'min:1'],
            'baris.*.siswa_id' => ['required', 'integer', 'exists:siswas,id'],
            'baris.*.nilai_pengetahuan' => ['nullable', 'integer', 'between:0,100'],
            'baris.*.nilai_keterampilan' => ['nullable', 'integer', 'between:0,100'],
            'baris.*.nilai_akhir' => ['nullable', 'integer', 'between:0,100'],
            'baris.*.sikap' => ['nullable', 'string', 'max:20'],
            'baris.*.capaian_kompetensi' => ['nullable', 'string', 'max:2000'],
        ]);

        $diizinkan = PembagianMengajar::query()
            ->milik($user->gtk, (int) $data['tahun_ajaran_id'])
            ->where('kelas_id', (int) $data['kelas_id'])
            ->where('mata_pelajaran_id', (int) $data['mata_pelajaran_id'])
            ->exists();
        abort_unless($diizinkan, 403);

        $kelas = Kelas::query()->findOrFail((int) $data['kelas_id']);
        $skema = TahunAjaran::skemaUntuk((int) $data['tahun_ajaran_id']);
        $semester = Semester::from((int) $data['semester']);
        $kkm = (int) $data['kkm'];
        $kolomSkor = array_keys($skema->kolomNilai());

        DB::transaction(function () use ($data, $kelas, $semester, $kkm, $kolomSkor): void {
            foreach ($data['baris'] as $row) {
                $siswaId = (int) $row['siswa_id'];
                $nilai = [];
                foreach ($kolomSkor as $kolom) {
                    $nilai[$kolom] = isset($row[$kolom]) && $row[$kolom] !== '' ? (int) $row[$kolom] : null;
                }
                if (array_key_exists('sikap', $row)) {
                    $nilai['sikap'] = filled($row['sikap']) ? (string) $row['sikap'] : null;
                }
                if (array_key_exists('capaian_kompetensi', $row)) {
                    $nilai['capaian_kompetensi'] = filled($row['capaian_kompetensi']) ? (string) $row['capaian_kompetensi'] : null;
                }

                $rapor = Rapor::query()
                    ->withTrashed()
                    ->where('siswa_id', $siswaId)
                    ->where('kelas_id', $kelas->id)
                    ->where('tahun_ajaran_id', (int) $data['tahun_ajaran_id'])
                    ->where('semester', $semester)
                    ->first();

                if ($rapor?->trashed()) {
                    continue;
                }

                $nilaiLama = $rapor?->nilaiRapor()->where('mata_pelajaran_id', (int) $data['mata_pelajaran_id'])->first();
                if ($nilaiLama === null && array_filter($nilai, filled(...)) === []) {
                    continue;
                }

                $rapor ??= Rapor::query()->create([
                    'siswa_id' => $siswaId,
                    'kelas_id' => $kelas->id,
                    'tahun_ajaran_id' => (int) $data['tahun_ajaran_id'],
                    'semester' => $semester,
                ]);

                NilaiRapor::query()->updateOrCreate(
                    ['rapor_id' => $rapor->id, 'mata_pelajaran_id' => (int) $data['mata_pelajaran_id']],
                    array_merge(['kkm' => $kkm], $nilai),
                );
            }
        });

        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }

    public function leger(Request $request, SusunLeger $susunLeger): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajarans,id'],
            'semester' => ['required', Rule::enum(Semester::class)],
        ]);

        $kelas = $user->gtk->kelasPerwalian()->findOrFail((int) $data['kelas_id']);
        $tahun = TahunAjaran::query()->findOrFail((int) $data['tahun_ajaran_id']);
        $semester = Semester::from((int) $data['semester']);

        $leger = $susunLeger->untuk($kelas, $tahun, $semester);

        $mapelList = $leger->mataPelajaran->map(fn ($m): array => [
            'id' => $m->id,
            'nama' => $m->nama,
            'singkatan' => $m->singkatan,
        ])->values()->all();

        $baris = collect($leger->baris)->map(fn (array $row): array => [
            'siswa_id' => $row['siswa']->id,
            'nis' => $row['siswa']->nis,
            'nama' => $row['siswa']->nama_lengkap,
            'jumlah' => $row['jumlah'],
            'rata_rata' => $row['rata_rata'],
            'peringkat' => $row['peringkat'],
            'nilai' => collect($row['nilai'])->map(fn (array $n, int $mapelId): array => [
                'mapel_id' => $mapelId,
                'skor' => $n['skor'],
                'kkm' => $n['kkm'],
            ])->values()->all(),
        ])->values()->all();

        return response()->json([
            'judul' => $leger->judul(),
            'keterangan_kolom' => $leger->keteranganKolom(),
            'mapel' => $mapelList,
            'baris' => $baris,
        ]);
    }

    public function raporIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajarans,id'],
            'semester' => ['required', Rule::enum(Semester::class)],
        ]);

        $kelas = $user->gtk->kelasPerwalian()->findOrFail((int) $data['kelas_id']);
        $semester = Semester::from((int) $data['semester']);

        $raporList = Rapor::query()
            ->with('siswa')
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', (int) $data['tahun_ajaran_id'])
            ->where('semester', $semester)
            ->get()
            ->sortBy(fn (Rapor $r): string => $r->siswa->nama_lengkap)
            ->toBase()
            ->map(fn (Rapor $r): array => [
                'id' => $r->id,
                'siswa_id' => $r->siswa_id,
                'nis' => $r->siswa->nis,
                'nama' => $r->siswa->nama_lengkap,
                'sakit' => $r->sakit,
                'izin' => $r->izin,
                'tanpa_keterangan' => $r->tanpa_keterangan,
                'catatan' => $r->catatan,
                'keputusan' => $r->keputusan?->value,
                'keputusan_label' => $r->keputusan?->getLabel(),
            ])
            ->values()
            ->all();

        return response()->json([
            'keputusan_opsi' => collect(KeputusanKenaikan::cases())->mapWithKeys(fn (KeputusanKenaikan $k): array => [$k->value => $k->getLabel()])->all(),
            'rapor' => $raporList,
        ]);
    }

    public function raporUpdate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->gtk !== null && $user->gtk->is_aktif, 403);

        $rapor = Rapor::query()->findOrFail($id);
        abort_unless($user->gtk->kelasPerwalian()->whereKey($rapor->kelas_id)->exists(), 403);

        $data = $request->validate([
            'sakit' => ['nullable', 'integer', 'min:0', 'max:365'],
            'izin' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tanpa_keterangan' => ['nullable', 'integer', 'min:0', 'max:365'],
            'catatan' => ['nullable', 'string', 'max:3000'],
            'keputusan' => ['nullable', Rule::enum(KeputusanKenaikan::class)],
        ]);

        $rapor->update($data);

        return response()->json(['message' => 'Data rapor siswa diperbarui.']);
    }
}
