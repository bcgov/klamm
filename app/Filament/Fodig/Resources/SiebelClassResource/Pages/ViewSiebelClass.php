<?php

namespace App\Filament\Fodig\Resources\SiebelClassResource\Pages;

use App\Filament\Fodig\Resources\SiebelClassResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelClass extends ViewRecord
{
    protected static string $resource = SiebelClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
