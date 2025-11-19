<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymizationMethod extends CreateRecord
{
    protected static string $resource = AnonymizationMethodResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Anonymization method created';
    }
}
