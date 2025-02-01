<?php

namespace App\Filament\Bre\Widgets;

use App\Models\BREField;
use App\Models\BRERule;
use App\Models\Form;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class BREStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $newestRule = BRERule::latest()->first();
        $newestField = BREField::latest()->first();

        $totalRules = BRERule::count();
        $totalFields = BREField::count();

        $rulesWithBO = BRERule::whereHas('breInputs.siebelBusinessObjects')
            ->orWhereHas('breOutputs.siebelBusinessObjects')
            ->count();
        $rulesWithBC = BRERule::whereHas('breInputs.siebelBusinessComponents')
            ->orWhereHas('breOutputs.siebelBusinessComponents')
            ->count();
        $fieldsWithBO = BREField::whereHas('siebelBusinessObjects')->count();
        $fieldsWithBC = BREField::whereHas('siebelBusinessComponents')->count();

        // Weekly rule counts for the last 8 weeks
        $weeklyRuleCounts = collect(range(7, 0))
            ->map(function ($weekAgo) {
                $startOfWeek = now()->subWeeks($weekAgo)->startOfWeek();
                $endOfWeek = now()->subWeeks($weekAgo)->endOfWeek();

                return BRERule::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
            })
            ->toArray();
        $currentWeek = end($weeklyRuleCounts);
        $previousWeek = prev($weeklyRuleCounts);
        $trend = $currentWeek - $previousWeek;
        $trendIcon = $trend > 0 ? 'heroicon-m-arrow-trending-up' : ($trend < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-s-arrow-long-right');
        $trendColor = $trend >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Total Fields', $totalFields),
            Stat::make('Fields with Business Objects', $fieldsWithBO . ' (' . round(($fieldsWithBO / $totalFields) * 100) . '%)'),
            Stat::make('Fields with Business Components', $fieldsWithBC . ' (' . round(($fieldsWithBC / $totalFields) * 100) . '%)'),
            Stat::make('Total Rules', $totalRules)
                ->description($trend ? abs($trend) . ' ' . ($trend >= 0 ? 'increase' : 'decrease') : 'No change')
                ->descriptionIcon($trendIcon)
                ->chart($weeklyRuleCounts)
                ->color($trendColor),
            Stat::make('Rules with Business Objects', $rulesWithBO . ' (' . round(($rulesWithBO / $totalRules) * 100) . '%)'),
            Stat::make('Rules with Business Components', $rulesWithBC . ' (' . round(($rulesWithBC / $totalRules) * 100) . '%)'),
            Stat::make('Newest Field', new HtmlString('<a href="/bre/fields/' . $newestField?->name . '">' . $newestField?->name . '</a>')),
            Stat::make('Newest Rule', new HtmlString('<a href="/bre/rules/' . $newestRule?->name . '">' . $newestRule?->name . '</a>')),
            Stat::make('Rules Updated Last Month', BRERule::where('updated_at', '>=', now()->subMonth())->count()),
        ];
    }
}
