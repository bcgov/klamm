<?php

namespace App\Filament\Forms\Widgets;

use App\Models\BusinessArea;
use App\Models\Form;
use App\Models\Ministry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\Auth;

use Filament\Widgets\StatsOverviewWidget\Stat;

class FormsStatsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalForms = Form::count();
        $ministries = Ministry::withCount('forms')->get();

        $stats = [
            Stat::make('', Form::where('updated_at', '>=', now()->subWeek())->count())
                ->icon('heroicon-o-calendar')
                ->description('Forms edited last week'),

            Stat::make('', Form::where('updated_at', '>=', now()->subMonth())->count())
                ->icon('heroicon-o-calendar')
                ->description('Forms edited last month'),

            Stat::make('', Form::where('updated_at', '>=', now()->subYear())->count())
                ->icon('heroicon-o-calendar')
                ->description('Forms edited last year'),

            Stat::make('', $totalForms)
                ->icon('heroicon-o-hashtag')
                ->description('Total number of forms'),

            Stat::make('', Form::where('decommissioned', false)->count())
                ->icon('heroicon-o-check-circle')
                ->description('Active forms'),

            Stat::make('', Form::where('decommissioned', true)->count())
                ->icon('heroicon-o-x-circle')
                ->description('Inactive forms'),
        ];

        return $stats;
    }
}
