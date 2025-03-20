<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemFileField extends ViewRecord
{
    protected static string $resource = BoundarySystemFileFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
