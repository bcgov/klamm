<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFormatResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemFileFormat extends ViewRecord
{
    protected static string $resource = BoundarySystemFileFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
