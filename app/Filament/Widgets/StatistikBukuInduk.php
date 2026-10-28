<?php

namespace App\Filament\Widgets;

use App\Enums\JenisKelamin;
use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Models\MutasiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikBukuInduk extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('viewAny', Siswa::class) ?? false;
    }

    protected function getStats(): array
    {
        $tahunAjaranAktif = TahunAjaran::query()->where('is_aktif', true)->first();
        $semester = Semester::berdasarkanTanggal(now());

        $perJenisKelamin = Siswa::query()
            ->where('status', StatusSiswa::Aktif)
            ->toBase()
            ->selectRaw('jenis_kelamin, count(*) as jumlah')
            ->groupBy('jenis_kelamin')
            ->pluck('jumlah', 'jenis_kelamin');

        $jumlahAktif = (int) $perJenisKelamin->sum();

        $raporSemesterIni = $tahunAjaranAktif
            ? Rapor::query()->milikSiswaAktif()->whereBelongsTo($tahunAjaranAktif)->where('semester', $semester)->count()
            : 0;

        return [
            Stat::make('Siswa Aktif', $jumlahAktif)
                ->description('L '.($perJenisKelamin[JenisKelamin::LakiLaki->value] ?? 0).' · P '.($perJenisKelamin[JenisKelamin::Perempuan->value] ?? 0))
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Alumni (Lulus)', Siswa::query()->where('status', StatusSiswa::Lulus)->count())
                ->icon('heroicon-o-academic-cap'),
            Stat::make('Rapor Semester Ini', "{$raporSemesterIni} / {$jumlahAktif}")
                ->description($tahunAjaranAktif ? "{$tahunAjaranAktif->nama} · {$semester->getLabel()}" : 'Belum ada tahun ajaran aktif')
                ->color($jumlahAktif > 0 && $raporSemesterIni >= $jumlahAktif ? 'success' : 'warning')
                ->icon('heroicon-o-document-chart-bar'),
            Stat::make('Mutasi Tahun Ajaran Ini', $tahunAjaranAktif ? MutasiSiswa::query()->whereBelongsTo($tahunAjaranAktif)->count() : 0)
                ->description('Pindah masuk, pindah keluar, dan keluar')
                ->icon('heroicon-o-arrows-right-left'),
        ];
    }
}
