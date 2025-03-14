<?php

namespace App\Filament\Fodig\Resources\ReportLabelSourceResource\Pages;

use App\Filament\Fodig\Resources\ReportLabelSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReportLabelSource extends ViewRecord
{
    protected static string $resource = ReportLabelSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
