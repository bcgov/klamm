<?php

namespace App\Filament\Forms\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Forms\Widgets\FormsDescriptionWidget;
use App\Filament\Forms\Widgets\YourFormsWidget;
use App\Filament\Forms\Widgets\FormsStatsWidget;
use App\Filament\Forms\Widgets\YourFormsLogsWidget;
use App\Filament\Forms\Widgets\MinistryGraphWidget;
use App\Filament\Forms\Widgets\FormMigrationWidget;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use App\Models\BusinessArea;
use Illuminate\Support\Facades\Gate;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

class FormsDashboard extends BaseDashboard
{
    use HasFiltersForm;
    use HasFiltersAction;
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Forms Modernization Project';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int | string | array
    {
        return 1;
    }

    public ?string $businessAreaId = null;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         FilterAction::make('Business Area')
    //             ->label('Filter IE 11 Compatibility Forms by Business Area')
    //             ->form([
    //                 Select::make('businessAreaId')
    //                     ->label('Business Area')
    //                     ->options(
    //                         BusinessArea::orderBy('name')->pluck('name', 'id')->toArray()
    //                     )
    //                     ->searchable()
    //                     ->placeholder('All Business Areas'),
    //             ])
    //             ->visible(fn() => Gate::allows('admin'))

    //     ];
    // }

    public function getWidgets(): array
    {
        return [
            FormsDescriptionWidget::class,
            FormMigrationWidget::class,
            YourFormsWidget::class,
            YourFormsLogsWidget::class,
            FormsStatsWidget::class,
            MinistryGraphWidget::class,
        ];
    }
}
