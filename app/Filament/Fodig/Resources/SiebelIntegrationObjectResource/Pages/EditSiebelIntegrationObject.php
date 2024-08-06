<?php

namespace App\Filament\Fodig\Resources\SiebelIntegrationObjectResource\Pages;

use App\Filament\Fodig\Resources\SiebelIntegrationObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelIntegrationObject extends EditRecord
{
    protected static string $resource = SiebelIntegrationObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
