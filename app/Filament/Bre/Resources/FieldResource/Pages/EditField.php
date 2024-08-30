<?php

namespace App\Filament\Bre\Resources\FieldResource\Pages;

use App\Filament\Bre\Resources\FieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditField extends EditRecord
{
    protected static string $resource = FieldResource::class;
    protected static ?string $title = 'Edit BRE Rule Field';
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
