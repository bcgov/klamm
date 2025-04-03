<?php

namespace App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemModeOfTransfers extends ListRecords
{
    protected static string $resource = BoundarySystemModeOfTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
