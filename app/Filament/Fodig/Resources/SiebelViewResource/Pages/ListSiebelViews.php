<?php

namespace App\Filament\Fodig\Resources\SiebelViewResource\Pages;

use App\Filament\Fodig\Resources\SiebelViewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelViews extends ListRecords
{
    protected static string $resource = SiebelViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
