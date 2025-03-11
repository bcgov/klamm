<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFileFields extends ListRecords
{
    protected static string $resource = BoundarySystemFileFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
