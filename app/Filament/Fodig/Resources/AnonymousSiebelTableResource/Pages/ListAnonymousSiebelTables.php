<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelTableResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelTableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousSiebelTables extends ListRecords
{
    protected static string $resource = AnonymousSiebelTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
