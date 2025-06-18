<?php

namespace App\Filament\Forms\Resources\SelectOptionFormElementResource\Pages;

use App\Filament\Forms\Resources\SelectOptionFormElementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSelectOptionFormElement extends EditRecord
{
    protected static string $resource = SelectOptionFormElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
