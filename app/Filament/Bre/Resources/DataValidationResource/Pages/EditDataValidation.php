<?php

namespace App\Filament\Bre\Resources\DataValidationResource\Pages;

use App\Filament\Bre\Resources\DataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataValidation extends EditRecord
{
    protected static string $resource = DataValidationResource::class;
    protected static ?string $title = 'Edit Field Data Validation';

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
