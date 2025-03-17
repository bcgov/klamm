<?php

namespace App\Filament\Reports\Resources\ReportBusinessAreaResource\Pages;

use App\Filament\Reports\Resources\ReportBusinessAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReportBusinessArea extends ViewRecord
{
    protected static string $resource = ReportBusinessAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
