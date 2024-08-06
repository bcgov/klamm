<?php

namespace App\Filament\Forms\Resources\SelectOptionsResource\Pages;

use App\Filament\Forms\Resources\SelectOptionsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSelectOptions extends EditRecord
{
    protected static string $resource = SelectOptionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
