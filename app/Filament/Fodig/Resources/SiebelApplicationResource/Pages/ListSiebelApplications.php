<?php

namespace App\Filament\Fodig\Resources\SiebelApplicationResource\Pages;

use App\Filament\Fodig\Resources\SiebelApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelApplications extends ListRecords
{
    protected static string $resource = SiebelApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
