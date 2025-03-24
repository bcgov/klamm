<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileSeparatorResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileSeparatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFileSeparators extends ListRecords
{
    protected static string $resource = BoundarySystemFileSeparatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
