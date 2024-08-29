<?php

namespace App\Filament\Bre\Resources\BREDataValidationResource\Pages;

use App\Filament\Bre\Resources\BREDataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBREDataValidation extends ViewRecord
{
    protected static string $resource = BREDataValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
