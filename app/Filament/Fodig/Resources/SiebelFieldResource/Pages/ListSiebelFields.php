<?php

namespace App\Filament\Fodig\Resources\SiebelFieldResource\Pages;

use App\Filament\Fodig\Resources\SiebelFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelFields extends ListRecords
{
    protected static string $resource = SiebelFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
