<?php

namespace App\Filament\Forms\Resources\FormFieldValidatorResource\Pages;

use App\Filament\Forms\Resources\FormFieldValidatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormFieldValidator extends ViewRecord
{
    protected static string $resource = FormFieldValidatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
