<?php

namespace App\Services\Harian;

use App\Enums\Semester;
use App\Models\CatatanPiket;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\PresensiGtk;
use App\Models\PresensiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PresensiHarian
{
    public function versi(int $kelas, string $tanggal): string
    {
        return hash('sha256', PresensiSiswa::query()->where('kelas_id', $kelas)->where('tanggal', $tanggal)->orderBy('siswa_id')->get()->toJson());
    }

    /** @param list<array<string, mixed>> $baris */
    public function simpanKelas(User $user, Kelas $kelas, string $tanggal, array $baris, string $versi, ?string $alasan = null): void
    {
        abort_unless(AksesHarian::kelas($user, $kelas, $tanggal), 403);
        $date = AksesHarian::tanggal($tanggal, true);
        $tahun = AksesHarian::tahun($tanggal);
        if ($tanggal !== AksesHarian::hariIni() && blank($alasan)) {
            throw ValidationException::withMessages(['alasan' => 'Isi alasan pencatatan presensi tanggal lampau.']);
        }
        Validator::make(['baris' => $baris, 'alasan' => $alasan], [
            'baris' => ['required', 'array', 'min:1'], 'baris.*.siswa_id' => ['required', 'integer', 'distinct'],
            'baris.*.status' => ['required', Rule::in(array_keys(PresensiSiswa::STATUS))],
            'baris.*.terlambat_menit' => ['required', 'integer', 'between:0,600'],
            'baris.*.catatan' => ['nullable', 'string', 'max:500'], 'alasan' => ['nullable', 'string', 'max:500'],
        ])->validate();
        $ids = Siswa::query()->anggotaKelas($kelas->id, $tahun->id)->orderBy('id')->pluck('id')->all();
        $submitted = array_map(fn (array $row): int => (int) $row['siswa_id'], $baris);
        sort($submitted);
        if ($submitted !== $ids) {
            throw ValidationException::withMessages(['baris' => 'Daftar siswa berubah atau tidak sesuai kelas. Muat ulang daftar siswa.']);
        }
        DB::transaction(function () use ($kelas, $tanggal, $baris, $versi, $alasan, $tahun, $date, $user, $ids): void {
            Kelas::query()->whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->versi($kelas->id, $tanggal), $versi)) {
                throw ValidationException::withMessages(['baris' => 'Presensi baru saja diubah petugas lain. Muat ulang sebelum menyimpan.']);
            }
            foreach ($baris as $row) {
                if ($row['status'] !== 'hadir' && (int) $row['terlambat_menit'] > 0) {
                    throw ValidationException::withMessages(['baris' => 'Keterlambatan hanya boleh dicatat untuk siswa hadir.']);
                }
                $record = PresensiSiswa::query()->firstOrNew(['tanggal' => $tanggal, 'siswa_id' => $row['siswa_id']]);
                $record->fill([
                    'kelas_id' => $kelas->id, 'tahun_ajaran_id' => $tahun->id, 'semester' => Semester::berdasarkanTanggal($date)->value,
                    'status' => $row['status'], 'terlambat_menit' => $row['terlambat_menit'], 'catatan' => $row['catatan'] ?? null,
                ]);
                if ($record->exists && $record->isDirty()) {
                    if (blank($alasan)) {
                        throw ValidationException::withMessages(['alasan' => 'Isi alasan koreksi untuk mengubah presensi yang sudah tersimpan.']);
                    }
                    if ($record->isDirty('kelas_id') && ! AksesHarian::pengelola($user)) {
                        abort(403);
                    }
                    $record->alasan_koreksi = $alasan;
                }
                if (! $record->exists || $record->isDirty()) {
                    if ($tanggal !== AksesHarian::hariIni()) {
                        $record->alasan_koreksi = $alasan;
                    }
                    $record->pencatat_id = $user->id;
                    $record->save();
                }
            }
            RekapPresensi::sinkron($ids, $tahun->id, Semester::berdasarkanTanggal($date));
        });
    }

    public function gtk(User $user, Gtk $gtk, string $tanggal, string $status, ?string $catatan = null): PresensiGtk
    {
        AksesHarian::tanggal($tanggal);
        $piket = AksesHarian::piket($user, $tanggal);
        if ($tanggal !== AksesHarian::hariIni() && (! $piket || blank($catatan))) {
            throw ValidationException::withMessages(['catatan' => 'Pencatatan tanggal lampau hanya oleh petugas piket dan wajib disertai alasan.']);
        }
        abort_unless($gtk->is_aktif && ($piket || ($gtk->user_id === $user->id && $status === 'hadir')), 403);
        Validator::make(compact('status', 'catatan'), ['status' => ['required', Rule::in(array_keys(PresensiGtk::STATUS))], 'catatan' => ['nullable', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($user, $gtk, $tanggal, $status, $catatan, $piket): PresensiGtk {
            Gtk::query()->whereKey($gtk->id)->lockForUpdate()->firstOrFail();
            $record = PresensiGtk::query()->firstOrNew(['gtk_id' => $gtk->id, 'tanggal' => $tanggal]);
            if ($record->exists && $record->status === $status && blank($catatan)) {
                return $record;
            }
            if ($record->exists && ($record->status !== $status || $record->catatan !== $catatan)) {
                abort_unless($piket, 403);
                if (blank($catatan)) {
                    throw ValidationException::withMessages(['catatan' => 'Isi alasan perubahan kehadiran GTK.']);
                }
            }
            $record->fill(['status' => $status, 'catatan' => $catatan, 'pencatat_id' => $user->id, 'dicatat_pada' => now()])->save();

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    public function piket(User $user, string $tanggal, array $data): CatatanPiket
    {
        abort_unless(AksesHarian::piket($user, $tanggal), 403);
        $date = AksesHarian::tanggal($tanggal);
        $tahun = AksesHarian::tahun($tanggal);
        $valid = Validator::make($data, [
            'jenis' => ['required', Rule::in(array_keys(CatatanPiket::JENIS))],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'], 'siswa_id' => ['nullable', 'integer', 'exists:siswas,id'],
            'gtk_id' => ['nullable', 'integer', 'exists:gtks,id'], 'terlambat_menit' => ['required', 'integer', 'between:0,600'],
            'jam_pelajaran' => ['nullable', 'string', 'max:100'], 'kejadian' => ['required', 'string', 'max:3000'], 'tindak_lanjut' => ['nullable', 'string', 'max:3000'],
        ])->validate();
        if (in_array($valid['jenis'], ['terlambat', 'izin_keluar'], true)
            && ! Siswa::query()->anggotaKelas((int) ($valid['kelas_id'] ?? 0), $tahun->id)->whereKey($valid['siswa_id'] ?? 0)->exists()) {
            throw ValidationException::withMessages(['siswa_id' => 'Pilih siswa yang terdaftar di kelas dan tahun ajaran tersebut.']);
        }
        if ($valid['jenis'] === 'guru_tidak_hadir' && empty($valid['gtk_id'])) {
            throw ValidationException::withMessages(['gtk_id' => 'Pilih GTK yang tidak hadir.']);
        }
        if ($valid['jenis'] === 'kelas_kosong' && empty($valid['kelas_id'])) {
            throw ValidationException::withMessages(['kelas_id' => 'Pilih kelas yang kosong.']);
        }

        return DB::transaction(function () use ($valid, $user, $tanggal, $tahun, $date): CatatanPiket {
            if ($valid['jenis'] === 'terlambat') {
                AksesHarian::tanggal($tanggal, true);
                if ($valid['terlambat_menit'] < 1) {
                    throw ValidationException::withMessages(['terlambat_menit' => 'Isi durasi keterlambatan.']);
                }
                Kelas::query()->whereKey($valid['kelas_id'])->lockForUpdate()->firstOrFail();
                $presensi = PresensiSiswa::query()->firstOrNew(['tanggal' => $tanggal, 'siswa_id' => $valid['siswa_id']]);
                $presensi->fill([
                    'kelas_id' => $valid['kelas_id'], 'tahun_ajaran_id' => $tahun->id, 'semester' => Semester::berdasarkanTanggal($date)->value,
                    'status' => 'hadir', 'terlambat_menit' => $valid['terlambat_menit'], 'catatan' => mb_substr($valid['kejadian'], 0, 500),
                    'alasan_koreksi' => mb_substr('Catatan piket: '.$valid['kejadian'], 0, 500), 'pencatat_id' => $user->id,
                ])->save();
                RekapPresensi::sinkron([(int) $valid['siswa_id']], $tahun->id, Semester::berdasarkanTanggal($date));
            }

            return CatatanPiket::query()->create([...$valid, 'tanggal' => $tanggal, 'pencatat_id' => $user->id]);
        });
    }

    public function kunciRapor(User $user, Rapor $rapor, bool $kunci, ?string $alasan = null): void
    {
        Validator::make(compact('alasan'), ['alasan' => ['nullable', 'string', 'max:500']])->validate();
        abort_unless(AksesHarian::pengelola($user) || ($user->gtk?->is_aktif && $rapor->kelas?->wali_kelas_id === $user->gtk->id), 403);
        if (! $kunci && blank($alasan)) {
            throw ValidationException::withMessages(['alasan' => 'Alasan membuka koreksi wajib diisi.']);
        }
        DB::transaction(function () use ($rapor, $kunci, $alasan): void {
            // Use the same lock order as class attendance to serialize a
            // submission with a concurrent report-card approval.
            Kelas::query()->whereKey($rapor->kelas_id)->lockForUpdate()->firstOrFail();
            $tersimpan = Rapor::query()->whereKey($rapor->id)->lockForUpdate()->firstOrFail();
            if ($kunci && $tersimpan->presensi_dikunci_at === null) {
                $tersimpan->fill(RekapPresensi::siswa($tersimpan->siswa_id, $tersimpan->tahun_ajaran_id, $tersimpan->semester) ?? []);
            }
            $tersimpan->fill(['presensi_dikunci_at' => $kunci ? now() : null, 'alasan_koreksi_presensi' => $alasan])->save();
            $rapor->refresh();
        });
    }
}
