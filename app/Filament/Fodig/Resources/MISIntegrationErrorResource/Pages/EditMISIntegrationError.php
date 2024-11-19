<?php

namespace App\Filament\Fodig\Resources\MISIntegrationErrorResource\Pages;

use App\Filament\Fodig\Resources\MISIntegrationErrorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMISIntegrationError extends EditRecord
{
    protected static string $resource = MISIntegrationErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
