<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessComponentResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessComponentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelBusinessComponents extends ListRecords
{
    protected static string $resource = SiebelBusinessComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
