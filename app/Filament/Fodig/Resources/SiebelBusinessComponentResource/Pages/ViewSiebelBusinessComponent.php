<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessComponentResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessComponentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelBusinessComponent extends ViewRecord
{
    protected static string $resource = SiebelBusinessComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
