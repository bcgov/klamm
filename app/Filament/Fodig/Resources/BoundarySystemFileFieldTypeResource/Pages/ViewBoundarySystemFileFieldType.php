<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemFileFieldType extends ViewRecord
{
    protected static string $resource = BoundarySystemFileFieldTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
