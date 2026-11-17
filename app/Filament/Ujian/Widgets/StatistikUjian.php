<?php

namespace App\Filament\Ujian\Widgets;

use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\PesertaUjian;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikUjian extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $soalQuery = SoalResource::getEloquentQuery();
        $totalSoal = (clone $soalQuery)->count();
        $soalSiap = (clone $soalQuery)->where('is_siap', true)->count();
        $soalDraf = $totalSoal - $soalSiap;

        $ujianQuery = UjianResource::getEloquentQuery();
        $totalUjian = (clone $ujianQuery)->count();
        $ujianMendatang = (clone $ujianQuery)->whereDate('tanggal', '>=', now()->toDateString())->count();
        $ujianIds = (clone $ujianQuery)->select('ujians.id');

        $pesertaDinilaiQuery = PesertaUjian::query()
            ->whereIn('ujian_id', $ujianIds)
            ->whereNotNull('nilai');

        $pesertaDinilai = (clone $pesertaDinilaiQuery)->count();
        $rataRataNilai = (clone $pesertaDinilaiQuery)->avg('nilai');

        return [
            Stat::make('Bank Soal', number_format($totalSoal))
                ->description("{$soalSiap} siap pakai · {$soalDraf} draf")
                ->descriptionIcon(Heroicon::OutlinedQuestionMarkCircle)
                ->color($soalSiap > 0 ? 'success' : 'gray')
                ->url(SoalResource::getUrl('index')),

            Stat::make('Total Ujian', number_format($totalUjian))
                ->description($ujianMendatang > 0 ? "{$ujianMendatang} ujian terjadwal" : 'Belum ada jadwal mendatang')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('primary')
                ->url(UjianResource::getUrl('index')),

            Stat::make('Peserta Dinilai', number_format($pesertaDinilai))
                ->description('Lembar jawaban sudah dikoreksi')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('info'),

            Stat::make('Rata-rata Nilai', $rataRataNilai !== null ? number_format((float) $rataRataNilai, 1) : '—')
                ->description($rataRataNilai !== null ? 'Dari seluruh peserta dinilai' : 'Belum ada nilai masuk')
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color($rataRataNilai === null ? 'gray' : ((float) $rataRataNilai >= 75 ? 'success' : 'warning')),
        ];
    }
}
