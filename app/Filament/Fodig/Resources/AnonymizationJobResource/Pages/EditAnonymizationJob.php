<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Pages\Concerns\SyncsAnonymizationJobSelection;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationJob extends EditRecord
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
                ->action(fn() => $this->downloadReadinessReportFromForm($this->record)),
            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Duplicate anonymization job?')
                ->modalDescription('This creates a new draft job with the same settings, scope, and column selections. The SQL script is regenerated for the new copy.')
                ->modalSubmitActionLabel('Duplicate job')
                ->action(function () {
                    $duplicate = AnonymizationJobResource::duplicateJob($this->record);

                    return $this->redirect(AnonymizationJobResource::getUrl('edit', ['record' => $duplicate]));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Anonymization job updated';
    }

    protected function afterSave(): void
    {
        $this->syncSelectionAndQueueSql($this->record, $this->form->getState());
    }
}
