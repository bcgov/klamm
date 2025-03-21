<?php

namespace App\Filament\Fodig\Resources\ErrorActorResource\Pages;

use App\Filament\Fodig\Resources\ErrorActorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListErrorActors extends ListRecords
{
    protected static string $resource = ErrorActorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
