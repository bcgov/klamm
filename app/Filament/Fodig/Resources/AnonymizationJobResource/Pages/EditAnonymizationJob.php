<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Jobs\GenerateAnonymizationJobSql;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationJob extends EditRecord
{
    protected static string $resource = AnonymizationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Anonymization job updated';
    }

    protected function afterSave(): void
    {
        GenerateAnonymizationJobSql::dispatch($this->record->getKey());
    }
}
