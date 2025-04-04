<?php

namespace App\Filament\Reports\Resources\ReportEntryResource\Pages;

use App\Filament\Reports\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListReportEntries extends ListRecords
{
    protected static string $resource = ReportEntryResource::class;

    protected static ?string $title = 'Report Label Dictionary';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Create Dictionary Entry'),
        ];
    }

    public function getSubheading(): HtmlString
    {
        return new HtmlString(view('filament.reports.report-entry.subheading')->render());
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
