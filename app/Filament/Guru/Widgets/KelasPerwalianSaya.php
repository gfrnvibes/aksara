<?php

namespace App\Filament\Guru\Widgets;

use App\Enums\Semester;
use App\Filament\Guru\Pages\RaporKelasSaya;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * For homeroom teachers: each class they look after, with how many of its students have a homeroom note on this
 * semester's report card.
 */
class KelasPerwalianSaya extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Kelas Perwalian Saya';

    public static function canView(): bool
    {
        return RaporKelasSaya::canAccess();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        $tahunAjaran = TahunAjaran::query()->where('is_aktif', true)->first();
        $semester = Semester::berdasarkanTanggal(now());

        if (! $user instanceof User || $user->gtk === null || $tahunAjaran === null) {
            return [];
        }

        return $user->gtk->kelasPerwalian()
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get()
            ->map(function (Kelas $kelas) use ($tahunAjaran, $semester): Stat {
                $anggota = Siswa::query()->anggotaKelas($kelas->id, $tahunAjaran->id)->pluck('id');
                $bercatatan = Rapor::query()
                    ->whereIn('siswa_id', $anggota)
                    ->where('tahun_ajaran_id', $tahunAjaran->id)
                    ->where('semester', $semester)
                    ->whereNotNull('catatan')
                    ->count();

                return Stat::make("Kelas {$kelas->nama}", "{$anggota->count()} siswa")
                    ->description("{$bercatatan} rapor sudah ada catatan wali kelas ({$semester->getLabel()})")
                    ->color($bercatatan >= $anggota->count() && $anggota->isNotEmpty() ? 'success' : 'warning')
                    ->url(RaporKelasSaya::getUrl(['kelas' => $kelas->id]));
            })
            ->all();
    }
}
