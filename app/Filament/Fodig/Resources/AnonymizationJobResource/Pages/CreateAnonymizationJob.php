<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Jobs\GenerateAnonymizationJobSql;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymizationJob extends CreateRecord
{
    protected static string $resource = AnonymizationJobResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Anonymization job created';
    }

    protected function afterCreate(): void
    {
        GenerateAnonymizationJobSql::dispatch($this->record->getKey());
    }
}
