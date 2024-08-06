<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessServiceResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelBusinessService extends ViewRecord
{
    protected static string $resource = SiebelBusinessServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
