<?php

namespace App\Filament\Fodig\Resources\BoundarySystemResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystem extends ViewRecord
{
    protected static string $resource = BoundarySystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
