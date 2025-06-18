<?php

namespace App\Filament\Forms\Resources\FormFieldValidatorResource\Pages;

use App\Filament\Forms\Resources\FormFieldValidatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormFieldValidator extends EditRecord
{
    protected static string $resource = FormFieldValidatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
