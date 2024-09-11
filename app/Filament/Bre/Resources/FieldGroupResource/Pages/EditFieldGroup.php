<?php

namespace App\Filament\Bre\Resources\FieldGroupResource\Pages;

use App\Filament\Bre\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFieldGroup extends EditRecord
{
    protected static string $resource = FieldGroupResource::class;
    protected static ?string $title = 'Edit BRE Rule Field Group';
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
