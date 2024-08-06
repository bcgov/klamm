<?php

namespace App\Filament\Fodig\Resources\SiebelValueResource\Pages;

use App\Filament\Fodig\Resources\SiebelValueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelValues extends ListRecords
{
    protected static string $resource = SiebelValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
