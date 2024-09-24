<?php

namespace App\Filament\Bre\Resources\ValidationTypeResource\Pages;

use App\Filament\Bre\Resources\ValidationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditValidationType extends EditRecord
{
    protected static string $resource = ValidationTypeResource::class;
    protected static ?string $title = 'Edit BRE Validation Type';
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
