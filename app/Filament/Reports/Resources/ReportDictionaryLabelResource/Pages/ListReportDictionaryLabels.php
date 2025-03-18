<?php

namespace App\Filament\Reports\Resources\ReportDictionaryLabelResource\Pages;

use App\Filament\Reports\Resources\ReportDictionaryLabelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportDictionaryLabels extends ListRecords
{
    protected static string $resource = ReportDictionaryLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
