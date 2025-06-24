<?php

namespace App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;

use App\Filament\Forms\Resources\FormSchemaImporterResource;
use Filament\Resources\Pages\ListRecords;

class ListSchemaImport extends ListRecords
{
    protected static string $resource = FormSchemaImporterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }
}
