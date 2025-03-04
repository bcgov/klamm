<?php

namespace App\Filament\Fodig\Resources\BoundarySystemProcessResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemProcess extends ViewRecord
{
    protected static string $resource = BoundarySystemProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
