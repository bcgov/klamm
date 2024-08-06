<?php

namespace App\Filament\Fodig\Resources\SiebelApplicationResource\Pages;

use App\Filament\Fodig\Resources\SiebelApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelApplication extends ViewRecord
{
    protected static string $resource = SiebelApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
