<?php

namespace App\Filament\Ujian\Widgets;

use App\Enums\JenisSoal;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use Filament\Widgets\ChartWidget;

class DistribusiSoalChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Distribusi Jenis Soal';

    protected ?string $description = 'Komposisi bank soal berdasarkan bentuk instrumen';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = SoalResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('jenis, COUNT(*) as total')
            ->groupBy('jenis')
            ->pluck('total', 'jenis')
            ->all();

        $labels = [];
        $data = [];
        $colors = [];

        foreach (JenisSoal::cases() as $jenis) {
            $labels[] = $jenis->getLabel();
            $data[] = (int) ($counts[$jenis->value] ?? 0);
            $colors[] = match ($jenis) {
                JenisSoal::PilihanGanda => '#059669',
                JenisSoal::PgKompleks => '#0284c7',
                JenisSoal::BenarSalah => '#d97706',
                JenisSoal::Menjodohkan => '#7c3aed',
                JenisSoal::Esai => '#e11d48',
            };
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Soal',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
