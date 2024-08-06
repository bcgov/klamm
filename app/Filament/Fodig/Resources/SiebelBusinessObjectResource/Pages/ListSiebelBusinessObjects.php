<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessObjectResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelBusinessObjects extends ListRecords
{
    protected static string $resource = SiebelBusinessObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
