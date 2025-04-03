<?php

namespace App\Filament\Fodig\Resources\ErrorEntityResource\Pages;

use App\Filament\Fodig\Resources\ErrorEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListErrorEntities extends ListRecords
{
    protected static string $resource = ErrorEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
