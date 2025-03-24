<?php

namespace App\Filament\Fodig\Resources\BoundarySystemProcessResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemProcesses extends ListRecords
{
    protected static string $resource = BoundarySystemProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
