<?php

namespace App\Filament\Fodig\Resources\SiebelScreenResource\Pages;

use App\Filament\Fodig\Resources\SiebelScreenResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelScreen extends ViewRecord
{
    protected static string $resource = SiebelScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
