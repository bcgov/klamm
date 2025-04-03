<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFrequencyResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFrequencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemFrequencies extends ListRecords
{
    protected static string $resource = BoundarySystemFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
