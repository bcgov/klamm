<?php

namespace App\Filament\Reports\Resources\ReportLabelSourceResource\Pages;

use App\Filament\Reports\Resources\ReportLabelSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportLabelSources extends ListRecords
{
    protected static string $resource = ReportLabelSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
