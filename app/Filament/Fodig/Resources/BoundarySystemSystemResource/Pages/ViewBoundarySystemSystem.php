<?php

namespace App\Filament\Fodig\Resources\BoundarySystemSystemResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemSystemResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemSystem extends ViewRecord
{
    protected static string $resource = BoundarySystemSystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
