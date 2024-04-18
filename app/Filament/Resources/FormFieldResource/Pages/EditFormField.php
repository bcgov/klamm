<?php

namespace App\Filament\Resources\FormFieldResource\Pages;

use App\Filament\Resources\FormFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormField extends EditRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
