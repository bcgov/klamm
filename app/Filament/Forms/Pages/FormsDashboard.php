<?php

namespace App\Filament\Forms\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Forms\Widgets\FormsDescriptionWidget;
use App\Filament\Forms\Widgets\YourFormsWidget;
use App\Filament\Forms\Widgets\FormsStatsWidget;
use App\Filament\Forms\Widgets\YourFormsLogsWidget;
use App\Filament\Forms\Widgets\MinistryGraphWidget;

class FormsDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Forms Modernization Project';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int | string | array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            FormsDescriptionWidget::class,
            YourFormsWidget::class,
            YourFormsLogsWidget::class,
            FormsStatsWidget::class,
            MinistryGraphWidget::class,
        ];
    }
}
