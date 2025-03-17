<?php

namespace App\Filament\Reports\Resources\ReportBusinessAreaResource\Pages;

use App\Filament\Reports\Resources\ReportBusinessAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportBusinessArea extends EditRecord
{
    protected static string $resource = ReportBusinessAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
