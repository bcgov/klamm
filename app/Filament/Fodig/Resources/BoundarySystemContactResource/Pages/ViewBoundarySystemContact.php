<?php

namespace App\Filament\Fodig\Resources\BoundarySystemContactResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemContactResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemContact extends ViewRecord
{
    protected static string $resource = BoundarySystemContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
