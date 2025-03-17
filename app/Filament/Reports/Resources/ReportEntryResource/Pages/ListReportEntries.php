<?php

namespace App\Filament\Reports\Resources\ReportEntryResource\Pages;

use App\Filament\Reports\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportEntries extends ListRecords
{
    protected static string $resource = ReportEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
