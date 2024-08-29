<?php

namespace App\Filament\Bre\Resources\BREValidationTypeResource\Pages;

use App\Filament\Bre\Resources\BREValidationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBREValidationType extends EditRecord
{
    protected static string $resource = BREValidationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
