<?php

namespace App\Filament\Bre\Resources\DataTypeResource\Pages;

use App\Filament\Bre\Resources\DataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataType extends EditRecord
{
    protected static string $resource = DataTypeResource::class;
    protected static ?string $title = 'Edit BRE Rule Field Data Value Type';

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
