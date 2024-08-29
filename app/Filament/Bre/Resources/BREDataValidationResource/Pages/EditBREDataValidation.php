<?php

namespace App\Filament\Bre\Resources\BREDataValidationResource\Pages;

use App\Filament\Bre\Resources\BREDataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBREDataValidation extends EditRecord
{
    protected static string $resource = BREDataValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
