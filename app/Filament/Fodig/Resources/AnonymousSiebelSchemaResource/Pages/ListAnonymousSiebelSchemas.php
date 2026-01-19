<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousSiebelSchemas extends ListRecords
{
    protected static string $resource = AnonymousSiebelSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
