<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessObjectResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelBusinessObject extends ViewRecord
{
    protected static string $resource = SiebelBusinessObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
