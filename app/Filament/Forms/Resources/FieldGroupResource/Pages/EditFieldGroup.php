<?php

namespace App\Filament\Forms\Resources\FieldGroupResource\Pages;

use App\Filament\Forms\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFieldGroup extends EditRecord
{
    protected static string $resource = FieldGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
