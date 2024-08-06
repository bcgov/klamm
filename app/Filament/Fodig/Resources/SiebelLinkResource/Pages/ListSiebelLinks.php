<?php

namespace App\Filament\Fodig\Resources\SiebelLinkResource\Pages;

use App\Filament\Fodig\Resources\SiebelLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelLinks extends ListRecords
{
    protected static string $resource = SiebelLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
