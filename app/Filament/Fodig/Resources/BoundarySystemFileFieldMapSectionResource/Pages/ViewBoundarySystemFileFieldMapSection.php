<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemFileFieldMapSection extends ViewRecord
{
    protected static string $resource = BoundarySystemFileFieldMapSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
