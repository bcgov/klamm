<?php

namespace App\Filament\Fodig\Resources\BoundarySystemTagResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemTagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoundarySystemTags extends ListRecords
{
    protected static string $resource = BoundarySystemTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
