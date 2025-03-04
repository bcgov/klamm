<?php

namespace App\Filament\Fodig\Resources\BoundarySystemSystemResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemSystemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemSystems extends ListRecords
{
    protected static string $resource = BoundarySystemSystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
