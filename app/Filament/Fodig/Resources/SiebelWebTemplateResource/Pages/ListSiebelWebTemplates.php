<?php

namespace App\Filament\Fodig\Resources\SiebelWebTemplateResource\Pages;

use App\Filament\Fodig\Resources\SiebelWebTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiebelWebTemplates extends ListRecords
{
    protected static string $resource = SiebelWebTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
