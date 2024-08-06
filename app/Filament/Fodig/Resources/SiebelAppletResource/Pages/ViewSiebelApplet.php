<?php

namespace App\Filament\Fodig\Resources\SiebelAppletResource\Pages;

use App\Filament\Fodig\Resources\SiebelAppletResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelApplet extends ViewRecord
{
    protected static string $resource = SiebelAppletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
