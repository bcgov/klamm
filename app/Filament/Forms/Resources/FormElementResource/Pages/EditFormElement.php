<?php

namespace App\Filament\Forms\Resources\FormElementResource\Pages;

use App\Filament\Forms\Resources\FormElementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormElement extends EditRecord
{
    protected static string $resource = FormElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
