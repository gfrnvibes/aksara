<?php

namespace App\Services\Harian;

use App\Enums\Hari;
use App\Enums\Semester;
use App\Models\Gtk;
use App\Models\Jadwal;
use App\Models\JurnalMengajar;
use App\Models\User;
use App\Services\Jadwal\PolaJam;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PertemuanMengajar
{
    /** @return Collection<string, array<string, mixed>> */
    public function daftar(User $user, string $tanggal, bool $semua = false): Collection
    {
        Validator::make(['tanggal' => $tanggal], ['tanggal' => ['required', 'date_format:Y-m-d']])->validate();
        try {
            $tahun = AksesHarian::tahun($tanggal);
        } catch (ValidationException) {
            return collect();
        }
        $hari = Hari::dariTanggal(Carbon::parse($tanggal));
        $jadwal = Jadwal::terbitUntuk($tahun->id, Semester::berdasarkanTanggal(Carbon::parse($tanggal)));
        $piket = $semua && AksesHarian::piket($user, $tanggal);
        $gtk = $user->gtk;
        $items = collect();
        $pola = PolaJam::muat();
        $libur = $hari === null || AksesHarian::libur($tanggal);
        if ($jadwal !== null && ! $libur) {
            $rows = $jadwal->isi()->with(['kelas', 'pembagianMengajar.gtk', 'pembagianMengajar.mataPelajaran'])
                ->when(! $piket, fn ($q) => $q->whereHas('pembagianMengajar', fn ($p) => $p->where('gtk_id', $gtk->id ?? 0)))
                ->where('hari', $hari)->orderBy('pembagian_mengajar_id')->orderBy('jp_ke')->get();
            $previous = null;
            $key = null;
            foreach ($rows as $row) {
                if ($previous === null || $previous->pembagian_mengajar_id !== $row->pembagian_mengajar_id
                    || $previous->jp_ke + 1 !== $row->jp_ke || (! $pola->kosong() && ! $pola->satuSesi($hari, $previous->jp_ke, $row->jp_ke))) {
                    $key = $jadwal->id.':'.$row->pembagian_mengajar_id.':'.$row->jp_ke;
                    $assignment = $row->pembagianMengajar;
                    $items->put($key, [
                        'sumber_jadwal' => $key, 'tahun_ajaran_id' => $tahun->id, 'semester' => $jadwal->semester->value,
                        'guru_id' => $assignment->gtk_id, 'kelas_id' => $row->kelas_id, 'mata_pelajaran_id' => $assignment->mata_pelajaran_id,
                        'nama_guru' => $assignment->gtk->nama, 'nama_kelas' => $row->kelas->nama, 'nama_mapel' => $assignment->mataPelajaran->nama,
                        'jp_mulai' => $row->jp_ke, 'jp_selesai' => $row->jp_ke, 'mulai' => $pola->jamUntuk($hari, $row->jp_ke)?->mulai,
                        'waktu' => $pola->jamUntuk($hari, $row->jp_ke)?->rentang(), 'status' => 'belum_dicatat',
                        'id' => null, 'materi' => null, 'kegiatan' => null, 'refleksi' => null, 'tindak_lanjut' => null, 'catatan_asli' => null,
                        'pelaksana_id' => null, 'alasan' => null, 'dibantu_ai' => false,
                    ]);
                } else {
                    $item = $items->get($key);
                    $item['jp_selesai'] = $row->jp_ke;
                    $selesai = $pola->jamUntuk($hari, $row->jp_ke)?->selesai;
                    $item['waktu'] = filled($item['mulai']) && filled($selesai) ? substr($item['mulai'], 0, 5).'–'.substr($selesai, 0, 5) : null;
                    $items->put($key, $item);
                }
                $previous = $row;
            }
        }
        foreach (JurnalMengajar::query()->where('tanggal', $tanggal)
            ->when(! $piket, fn ($q) => $q->where('guru_id', $gtk->id ?? 0))->get() as $jurnal) {
            $key = $jurnal->sumber_jadwal;
            $items->put($key, [...($items->get($key) ?? []), ...$jurnal->getAttributes()]);
        }

        return $items;
    }

    /** @param array<string, mixed> $data */
    public function simpan(User $user, string $tanggal, string $key, array $data, bool $hadirSaja = false): JurnalMengajar
    {
        $date = AksesHarian::tanggal($tanggal, true);
        $item = $this->daftar($user, $tanggal, true)->get($key);
        abort_if($item === null, 403);
        $piket = AksesHarian::piket($user, $tanggal);
        $own = $user->gtk?->is_aktif && (int) $item['guru_id'] === $user->gtk->id;
        abort_unless($piket || $own, 403);
        if ($tanggal === AksesHarian::hariIni() && filled($item['mulai'] ?? null)
            && now('Asia/Jakarta')->lt($date->copy()->setTimeFromTimeString($item['mulai'])) && ! $piket) {
            throw ValidationException::withMessages(['materi' => 'Jam mengajar ini belum dimulai.']);
        }
        $status = $hadirSaja ? 'hadir' : ($data['status'] ?? 'terlaksana');
        Validator::make([...$data, 'status' => $status], [
            'status' => ['required', Rule::in(array_keys(JurnalMengajar::STATUS))],
            'materi' => [Rule::requiredIf(! $hadirSaja && $status !== 'tidak_terlaksana'), 'nullable', 'string', 'max:3000'],
            'catatan_asli' => ['nullable', 'string', 'max:3000'], 'kegiatan' => ['nullable', 'string', 'max:5000'],
            'refleksi' => ['nullable', 'string', 'max:3000'], 'tindak_lanjut' => ['nullable', 'string', 'max:3000'],
            'alasan' => ['nullable', 'string', 'max:500'], 'pelaksana_id' => ['nullable', 'integer', 'exists:gtks,id'], 'dibantu_ai' => ['nullable', 'boolean'],
        ])->validate();
        if (($tanggal !== AksesHarian::hariIni() || ! $own || in_array($status, ['digantikan', 'tidak_terlaksana', 'kegiatan_pengganti'])) && blank($data['alasan'] ?? null)) {
            throw ValidationException::withMessages(['alasan' => 'Isi alasan pencatatan pengganti, kegiatan khusus, atau tanggal lampau.']);
        }
        if (! $piket && in_array($status, ['digantikan', 'tidak_terlaksana'])) {
            abort(403);
        }
        $pelaksana = $status === 'tidak_terlaksana' ? null : ($status === 'digantikan' ? (int) ($data['pelaksana_id'] ?? 0) : (int) $item['guru_id']);
        if ($pelaksana !== null && ! Gtk::query()->aktif()->whereKey($pelaksana)->exists()) {
            throw ValidationException::withMessages(['pelaksana_id' => 'Pilih guru pelaksana yang aktif.']);
        }

        return DB::transaction(function () use ($user, $tanggal, $key, $data, $hadirSaja, $item, $status, $pelaksana, $own): JurnalMengajar {
            Gtk::query()->whereKey($item['guru_id'])->lockForUpdate()->firstOrFail();
            $record = JurnalMengajar::query()->firstOrNew(['tanggal' => $tanggal, 'sumber_jadwal' => $key]);
            if ($hadirSaja && $record->exists) {
                return $record;
            }
            $snapshot = array_intersect_key($item, array_flip(['tahun_ajaran_id', 'semester', 'guru_id', 'kelas_id', 'mata_pelajaran_id', 'nama_guru', 'nama_kelas', 'nama_mapel', 'jp_mulai', 'jp_selesai', 'waktu']));
            $record->fill([...$snapshot, 'status' => $status, 'pelaksana_id' => $pelaksana]);
            if ($hadirSaja) {
                $record->alasan = $data['alasan'] ?? null;
            }
            if (! $hadirSaja) {
                $record->fill(array_intersect_key($data, array_flip(['catatan_asli', 'materi', 'kegiatan', 'refleksi', 'tindak_lanjut', 'alasan', 'dibantu_ai'])));
            }
            if ($record->exists && $record->getRawOriginal('status') !== 'hadir' && $record->isDirty() && blank($data['alasan'] ?? null)) {
                throw ValidationException::withMessages(['alasan' => 'Isi alasan koreksi untuk mengubah jurnal tersimpan.']);
            }
            if ($own && $pelaksana === (int) $item['guru_id'] && $tanggal === AksesHarian::hariIni()) {
                app(PresensiHarian::class)->gtk($user, Gtk::query()->findOrFail($pelaksana), $tanggal, 'hadir');
            }
            $record->hadir_pada = $record->hadir_pada ?? ($status === 'tidak_terlaksana' ? null : now());
            $record->pencatat_id = $user->id;
            $record->save();

            return $record;
        });
    }
}
