<?php

namespace App\Filament\Forms\Resources\SelectableValueResource\Pages;

use App\Filament\Forms\Resources\SelectableValueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSelectableValue extends EditRecord
{
    protected static string $resource = SelectableValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
