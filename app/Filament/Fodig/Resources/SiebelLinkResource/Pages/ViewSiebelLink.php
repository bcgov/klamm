<?php

namespace App\Filament\Fodig\Resources\SiebelLinkResource\Pages;

use App\Filament\Fodig\Resources\SiebelLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelLink extends ViewRecord
{
    protected static string $resource = SiebelLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
