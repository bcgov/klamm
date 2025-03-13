<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemFileFieldMap extends ViewRecord
{
    protected static string $resource = BoundarySystemFileFieldMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
