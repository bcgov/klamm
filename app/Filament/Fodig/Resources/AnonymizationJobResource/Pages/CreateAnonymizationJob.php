<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Pages\Concerns\SyncsAnonymizationJobSelection;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymizationJob extends CreateRecord
{
    use SyncsAnonymizationJobSelection;

    protected static string $resource = AnonymizationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadReadiness')
                ->label('Download Readiness Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('secondary')
                ->outlined()
                ->action(fn() => $this->downloadReadinessReportFromForm($this->record ?? null)),
        ];
    }

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
        $this->syncSelectionAndQueueSql($this->record, $this->form->getState());
    }
}
