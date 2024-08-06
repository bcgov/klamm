<?php

namespace App\Filament\Fodig\Resources\SiebelAppletResource\Pages;

use App\Filament\Fodig\Resources\SiebelAppletResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelApplets extends ListRecords
{
    protected static string $resource = SiebelAppletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
