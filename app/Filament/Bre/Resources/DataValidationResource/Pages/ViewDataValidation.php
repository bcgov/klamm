<?php

namespace App\Filament\Bre\Resources\DataValidationResource\Pages;

use App\Filament\Bre\Resources\DataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDataValidation extends ViewRecord
{
    protected static string $resource = DataValidationResource::class;
    protected static ?string $title = 'View Field Data Validation';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
