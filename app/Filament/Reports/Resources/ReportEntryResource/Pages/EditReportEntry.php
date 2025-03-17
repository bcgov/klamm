<?php

namespace App\Filament\Reports\Resources\ReportEntryResource\Pages;

use App\Filament\Reports\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportEntry extends EditRecord
{
    protected static string $resource = ReportEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
