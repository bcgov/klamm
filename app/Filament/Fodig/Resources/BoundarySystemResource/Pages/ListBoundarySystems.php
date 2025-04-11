<?php

namespace App\Filament\Fodig\Resources\BoundarySystemResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystems extends ListRecords
{
    protected static string $resource = BoundarySystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }
}
