<?php

namespace App\Filament\Fodig\Resources\SiebelFieldResource\Pages;

use App\Filament\Fodig\Resources\SiebelFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelField extends ViewRecord
{
    protected static string $resource = SiebelFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
