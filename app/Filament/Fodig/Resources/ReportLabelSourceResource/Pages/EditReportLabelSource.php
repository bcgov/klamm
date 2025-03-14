<?php

namespace App\Filament\Fodig\Resources\ReportLabelSourceResource\Pages;

use App\Filament\Fodig\Resources\ReportLabelSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportLabelSource extends EditRecord
{
    protected static string $resource = ReportLabelSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
