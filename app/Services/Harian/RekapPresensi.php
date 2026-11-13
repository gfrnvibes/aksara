<?php

namespace App\Services\Harian;

use App\Enums\Semester;
use App\Models\PresensiSiswa;
use App\Models\Rapor;

class RekapPresensi
{
    /** @return array{sakit: int, izin: int, tanpa_keterangan: int}|null */
    public static function siswa(int $siswa, int $tahun, Semester $semester): ?array
    {
        $counts = PresensiSiswa::query()->where('siswa_id', $siswa)->where('tahun_ajaran_id', $tahun)
            ->where('semester', $semester->value)->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');
        if ($counts->isEmpty()) {
            return null;
        }

        return ['sakit' => (int) $counts->get('sakit', 0), 'izin' => (int) $counts->get('izin', 0), 'tanpa_keterangan' => (int) $counts->get('alpa', 0)];
    }

    /** @param list<int> $siswa */
    public static function sinkron(array $siswa, int $tahun, Semester $semester): void
    {
        foreach (Rapor::query()->whereIn('siswa_id', $siswa)->where('tahun_ajaran_id', $tahun)
            ->where('semester', $semester)->whereNull('presensi_dikunci_at')->get() as $rapor) {
            // Rapor::saving calculates its own recap; avoid running the same
            // aggregate twice for every student after a class submission.
            $rapor->save();
        }
    }
}
