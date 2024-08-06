<?php

namespace App\Filament\Fodig\Resources\SiebelScreenResource\Pages;

use App\Filament\Fodig\Resources\SiebelScreenResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelScreens extends ListRecords
{
    protected static string $resource = SiebelScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
