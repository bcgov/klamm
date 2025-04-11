<?php

namespace App\Filament\Fodig\Resources\BoundarySystemContactResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemContactResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemContacts extends ListRecords
{
    protected static string $resource = BoundarySystemContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
