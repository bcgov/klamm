<?php

namespace App\Filament\Fodig\Resources\SiebelViewResource\Pages;

use App\Filament\Fodig\Resources\SiebelViewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelView extends ViewRecord
{
    protected static string $resource = SiebelViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
