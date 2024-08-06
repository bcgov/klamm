<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessServiceResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelBusinessServices extends ListRecords
{
    protected static string $resource = SiebelBusinessServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
