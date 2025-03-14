<?php

namespace App\Filament\Fodig\Resources\ReportEntryResource\Pages;

use App\Filament\Fodig\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReportEntry extends ViewRecord
{
    protected static string $resource = ReportEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
