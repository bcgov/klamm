<?php

namespace App\Filament\Resources\DataTypeResource\Pages;

use App\Filament\Resources\DataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataType extends EditRecord
{
    protected static string $resource = DataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
