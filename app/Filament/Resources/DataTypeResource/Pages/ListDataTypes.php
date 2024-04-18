<?php

namespace App\Filament\Resources\DataTypeResource\Pages;

use App\Filament\Resources\DataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataTypes extends ListRecords
{
    protected static string $resource = DataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
