<?php

namespace App\Filament\Fodig\Resources\SiebelClassResource\Pages;

use App\Filament\Fodig\Resources\SiebelClassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelClasses extends ListRecords
{
    protected static string $resource = SiebelClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
