<?php

namespace App\Filament\Reports\Resources\ReportDictionaryLabelResource\Pages;

use App\Filament\Reports\Resources\ReportDictionaryLabelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportDictionaryLabel extends EditRecord
{
    protected static string $resource = ReportDictionaryLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
