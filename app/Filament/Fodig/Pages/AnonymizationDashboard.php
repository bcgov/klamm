<?php

namespace App\Filament\Fodig\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use App\Filament\Fodig\Widgets\AnonymizationActivityWidget;

class AnonymizationDashboard extends BaseDashboard
{
    use HasFiltersForm;
    use HasFiltersAction;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Anonymization Overview';
    protected static ?string $navigationLabel = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            AnonymizationActivityWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'lg' => 1,
        ];
    }
}
