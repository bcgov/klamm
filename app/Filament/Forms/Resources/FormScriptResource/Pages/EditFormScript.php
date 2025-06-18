<?php

namespace App\Filament\Forms\Resources\FormScriptResource\Pages;

use App\Filament\Forms\Resources\FormScriptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormScript extends EditRecord
{
    protected static string $resource = FormScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
