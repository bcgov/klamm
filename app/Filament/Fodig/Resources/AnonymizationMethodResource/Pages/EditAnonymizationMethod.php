<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationMethod extends EditRecord
{
    protected static string $resource = AnonymizationMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Anonymization method updated';
    }
}
