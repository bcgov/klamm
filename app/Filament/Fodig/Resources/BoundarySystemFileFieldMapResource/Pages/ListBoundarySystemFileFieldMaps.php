<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFileFieldMaps extends ListRecords
{
    protected static string $resource = BoundarySystemFileFieldMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
