<?php

namespace App\Filament\Fodig\Resources\SiebelWebPageResource\Pages;

use App\Filament\Fodig\Resources\SiebelWebPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelWebPages extends ListRecords
{
    protected static string $resource = SiebelWebPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
