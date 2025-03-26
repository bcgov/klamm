<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFileFieldTypes extends ListRecords
{
    protected static string $resource = BoundarySystemFileFieldTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
