<?php

namespace App\Filament\Fodig\Resources\DataGroupResource\Pages;

use App\Filament\Fodig\Resources\DataGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataGroups extends ListRecords
{
    protected static string $resource = DataGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
