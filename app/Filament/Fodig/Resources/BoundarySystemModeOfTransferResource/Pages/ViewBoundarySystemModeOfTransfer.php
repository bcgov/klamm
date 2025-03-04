<?php

namespace App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoundarySystemModeOfTransfer extends ViewRecord
{
    protected static string $resource = BoundarySystemModeOfTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
