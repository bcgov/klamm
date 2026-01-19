<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelDataTypeResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelDataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousSiebelDataTypes extends ListRecords
{
    protected static string $resource = AnonymousSiebelDataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
