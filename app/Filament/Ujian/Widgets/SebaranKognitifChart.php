<?php

namespace App\Filament\Ujian\Widgets;

use App\Enums\LevelKognitif;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use Filament\Widgets\ChartWidget;

class SebaranKognitifChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Sebaran Level Kognitif';

    protected ?string $description = 'Perbandingan soal siap pakai dan draf pada tiap level Bloom (C1–C6)';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = SoalResource::getEloquentQuery()
            ->toBase()
            ->whereNotNull('level_kognitif')
            ->selectRaw('level_kognitif, is_siap, COUNT(*) as total')
            ->groupBy('level_kognitif', 'is_siap')
            ->get();

        $siap = [];
        $draf = [];

        foreach ($rows as $row) {
            $level = (string) $row->level_kognitif;

            if ((bool) $row->is_siap) {
                $siap[$level] = (int) $row->total;
            } else {
                $draf[$level] = (int) $row->total;
            }
        }

        $labels = [];
        $dataSiap = [];
        $dataDraf = [];

        foreach (LevelKognitif::cases() as $level) {
            $labels[] = "{$level->value} ({$level->level()})";
            $dataSiap[] = $siap[$level->value] ?? 0;
            $dataDraf[] = $draf[$level->value] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Siap Pakai',
                    'data' => $dataSiap,
                    'backgroundColor' => '#10b981',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Draf',
                    'data' => $dataDraf,
                    'backgroundColor' => '#cbd5e1',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
