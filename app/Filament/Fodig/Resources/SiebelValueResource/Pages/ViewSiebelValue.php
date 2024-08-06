<?php

namespace App\Filament\Fodig\Resources\SiebelValueResource\Pages;

use App\Filament\Fodig\Resources\SiebelValueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelValue extends ViewRecord
{
    protected static string $resource = SiebelValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
