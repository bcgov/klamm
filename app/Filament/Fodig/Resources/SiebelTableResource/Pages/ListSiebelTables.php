<?php

namespace App\Filament\Fodig\Resources\SiebelTableResource\Pages;

use App\Filament\Fodig\Resources\SiebelTableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelTables extends ListRecords
{
    protected static string $resource = SiebelTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
