<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemFile extends ViewRecord
{
    protected static string $resource = BoundarySystemFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
