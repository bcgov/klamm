<?php

namespace App\Filament\Bre\Resources\BREValidationTypeResource\Pages;

use App\Filament\Bre\Resources\BREValidationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBREValidationType extends ViewRecord
{
    protected static string $resource = BREValidationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
