<?php

namespace App\Filament\Fodig\Resources\BoundarySystemTagResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemTagResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemTag extends ViewRecord
{
    protected static string $resource = BoundarySystemTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
