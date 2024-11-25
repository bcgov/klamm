<?php

namespace App\Filament\Fodig\Resources\MISIntegrationErrorResource\Pages;

use App\Filament\Fodig\Resources\MISIntegrationErrorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMISIntegrationErrors extends ListRecords
{
    protected static string $resource = MISIntegrationErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
