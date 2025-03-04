<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFormatResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFileFormats extends ListRecords
{
    protected static string $resource = BoundarySystemFileFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
