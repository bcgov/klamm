<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFiles extends ListRecords
{
    protected static string $resource = BoundarySystemFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
