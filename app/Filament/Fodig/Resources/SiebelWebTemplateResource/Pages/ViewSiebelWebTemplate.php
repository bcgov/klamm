<?php

namespace App\Filament\Fodig\Resources\SiebelWebTemplateResource\Pages;

use App\Filament\Fodig\Resources\SiebelWebTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelWebTemplate extends ViewRecord
{
    protected static string $resource = SiebelWebTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
