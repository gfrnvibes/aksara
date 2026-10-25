<?php

namespace App\Filament\Widgets;

use App\Enums\StatusSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikBukuInduk extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $tahunAjaranAktif = TahunAjaran::query()->where('is_aktif', true)->first();

        return [
            Stat::make('Siswa Aktif', Siswa::query()->where('status', StatusSiswa::Aktif)->count())
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Alumni (Lulus)', Siswa::query()->where('status', StatusSiswa::Lulus)->count())
                ->icon('heroicon-o-academic-cap'),
            Stat::make(
                'Rapor Tahun Ajaran Aktif',
                $tahunAjaranAktif ? Rapor::query()->whereBelongsTo($tahunAjaranAktif)->count() : 0,
            )
                ->description($tahunAjaranAktif->nama ?? 'Belum ada tahun ajaran aktif')
                ->icon('heroicon-o-document-chart-bar'),
        ];
    }
}
