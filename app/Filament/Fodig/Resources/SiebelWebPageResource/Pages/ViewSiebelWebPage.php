<?php

namespace App\Filament\Fodig\Resources\SiebelWebPageResource\Pages;

use App\Filament\Fodig\Resources\SiebelWebPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelWebPage extends ViewRecord
{
    protected static string $resource = SiebelWebPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
