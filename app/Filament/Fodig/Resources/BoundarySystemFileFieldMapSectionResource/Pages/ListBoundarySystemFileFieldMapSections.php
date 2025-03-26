<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFileFieldMapSections extends ListRecords
{
    protected static string $resource = BoundarySystemFileFieldMapSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
