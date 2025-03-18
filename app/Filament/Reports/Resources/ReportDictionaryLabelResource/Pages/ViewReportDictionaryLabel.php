<?php

namespace App\Filament\Reports\Resources\ReportDictionaryLabelResource\Pages;

use App\Filament\Reports\Resources\ReportDictionaryLabelResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReportDictionaryLabel extends ViewRecord
{
    protected static string $resource = ReportDictionaryLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
