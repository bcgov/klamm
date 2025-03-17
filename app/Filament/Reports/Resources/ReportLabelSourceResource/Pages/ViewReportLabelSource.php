<?php

namespace App\Filament\Reports\Resources\ReportLabelSourceResource\Pages;

use App\Filament\Reports\Resources\ReportLabelSourceResource;
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
