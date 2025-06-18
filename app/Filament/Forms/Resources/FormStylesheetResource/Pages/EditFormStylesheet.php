<?php

namespace App\Filament\Forms\Resources\FormStylesheetResource\Pages;

use App\Filament\Forms\Resources\FormStylesheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormStylesheet extends EditRecord
{
    protected static string $resource = FormStylesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
