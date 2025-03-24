<?php

namespace App\Filament\Fodig\Resources\ErrorIntegrationStateResource\Pages;

use App\Filament\Fodig\Resources\ErrorIntegrationStateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewErrorIntegrationState extends ViewRecord
{
    protected static string $resource = ErrorIntegrationStateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
