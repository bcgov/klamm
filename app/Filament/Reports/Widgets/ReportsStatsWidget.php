<?php

namespace App\Filament\Reports\Widgets;

use App\Models\ReportEntry;
use Filament\Widgets\ChartWidget;

class ReportsStatsWidget extends ChartWidget
{
    protected static ?string $heading = 'Data Matching Rate Distribution';

    protected function getData(): array
    {
        $counts = ReportEntry::query()
            ->selectRaw("
                COUNT(CASE WHEN data_matching_rate = 'low' THEN 1 END) as low,
                COUNT(CASE WHEN data_matching_rate = 'medium' THEN 1 END) as medium,
                COUNT(CASE WHEN data_matching_rate = 'high' THEN 1 END) as high,
                COUNT(CASE WHEN data_matching_rate IS NULL OR data_matching_rate = '' THEN 1 END) as unknown
            ")
            ->first()
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Data Matching Rate',
                    'data' => array_values($counts),
                    'backgroundColor' => ['#22C55E', '#EAB308', '#DC2626', '#6B7280'],
                ],
            ],
            'labels' => ['Low', 'Medium', 'High', 'Unknown'],
        ];
    }

    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => true,
                'position' => 'bottom',
            ],
        ],
        'scales' => [
            'x' => [
                'display' => false,
            ],
            'y' => [
                'display' => false,
            ],
        ],
    ];


    protected function getType(): string
    {
        return 'pie';
    }
}
