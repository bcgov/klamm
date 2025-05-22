<?php

namespace App\Filament\Forms\Widgets;

use App\Models\Ministry;
use Filament\Widgets\ChartWidget;

class MinistryGraphWidget extends ChartWidget
{
    protected static ?string $heading   = 'Number of Forms per Ministry';
    protected static string  $name      = 'ministry-graph-widget';
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $ministries = Ministry::withCount('forms')->get()->sortBy('name');
        $colors = [
            '#F8CF47',
            '#3D73B4',
            '#6CA893',
        ];

        $formCounts = $ministries->pluck('forms_count')->unique()->sort()->values()->toArray();
        $datasets = [];
        foreach ($ministries as $i => $ministry) {

            $data = array_fill(0, count($formCounts), 0);
            $countPosition = array_search($ministry->forms_count, $formCounts);
            $data[$countPosition] = $ministry->forms_count;

            $datasets[] = [
                'label' => str_replace('Ministry of ', '', $ministry->name),
                'data' => $data,
                'backgroundColor' => $colors[$i % count($colors)],
                'stack' => 'Stack 0',
            ];
        }

        return [
            'labels' => $formCounts,
            'datasets' => $datasets,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 20,
                        'padding' => 15,
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => true,
                ],
                'x' => [
                    'reverse' => true,
                    'stacked' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'barPercentage' => 1.0,
            'categoryPercentage' => 0.7,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
