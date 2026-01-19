<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelDataTypeResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelDataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymousSiebelDataType extends EditRecord
{
    protected static string $resource = AnonymousSiebelDataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
