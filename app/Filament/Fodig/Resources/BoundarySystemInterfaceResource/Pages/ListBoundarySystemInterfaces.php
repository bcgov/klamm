<?php

namespace App\Filament\Fodig\Resources\BoundarySystemInterfaceResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemInterfaces extends ListRecords
{
    protected static string $resource = BoundarySystemInterfaceResource::class;

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
