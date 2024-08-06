<?php

namespace App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource\Pages;

use App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelEimInterfaceTables extends ListRecords
{
    protected static string $resource = SiebelEimInterfaceTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
