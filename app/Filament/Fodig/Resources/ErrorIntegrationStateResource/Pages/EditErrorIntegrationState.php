<?php

namespace App\Filament\Fodig\Resources\ErrorIntegrationStateResource\Pages;

use App\Filament\Fodig\Resources\ErrorIntegrationStateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorIntegrationState extends EditRecord
{
    protected static string $resource = ErrorIntegrationStateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
