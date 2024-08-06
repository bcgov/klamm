<?php

namespace App\Filament\Forms\Widgets;

use App\Models\BusinessArea;
use App\Models\Form;
use App\Models\Ministry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class FormsStatsWidget extends BaseWidget
{
    protected function getCards(): array
    {
        $totalForms = Form::count();
        $totalBusinessAreas = BusinessArea::count();
        $ministries = Ministry::withCount('forms')->get();
        $businessAreas = BusinessArea::withCount('forms')->get();

        $statWidgetOutput = [
            Card::make('Total Forms', $totalForms),
            Card::make('Total Business Areas', $totalBusinessAreas),
            Card::make('Active Forms', Form::where('decommissioned', false)->count()),
            Card::make('Decommissioned Forms', Form::where('decommissioned', true)->count()),
            Card::make('Forms Edited Last Week', Form::where('updated_at', '>=', now()->subWeek())->count()),
            Card::make('Forms Edited Last Month', Form::where('updated_at', '>=', now()->subMonth())->count()),
            Card::make('Forms Edited Last Year', Form::where('updated_at', '>=', now()->subYear())->count()),
        ];

        foreach ($ministries as $ministry) {
            $statWidgetOutput[] = Card::make($ministry->name . ' Forms', $ministry->forms_count);
        }

        foreach ($businessAreas as $businessArea) {
            $statWidgetOutput[] = Card::make($businessArea->name . ' Forms', $businessArea->forms_count);
        }

        return $statWidgetOutput;
    }
}
