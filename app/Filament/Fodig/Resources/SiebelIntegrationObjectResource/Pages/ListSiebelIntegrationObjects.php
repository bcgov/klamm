<?php

namespace App\Filament\Fodig\Resources\SiebelIntegrationObjectResource\Pages;

use App\Filament\Fodig\Resources\SiebelIntegrationObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelIntegrationObjects extends ListRecords
{
    protected static string $resource = SiebelIntegrationObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
