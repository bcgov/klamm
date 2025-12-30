<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use App\Models\Anonymizer\AnonymizationMethods;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymizationMethod extends ViewRecord
{
    protected static string $resource = AnonymizationMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_version')
                ->label('New version')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->modalHeading('Create a new method version?')
                ->modalDescription('This will duplicate the method settings into a new record (existing job/column links remain on the current version).')
                ->modalSubmitActionLabel('Create version')
                ->action(function (AnonymizationMethods $record) {
                    $new = $record->createNewVersion();

                    return $this->redirect(AnonymizationMethodResource::getUrl('edit', ['record' => $new]));
                }),
            Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->requiresConfirmation(fn(AnonymizationMethods $record) => $record->isInUse())
                ->modalHeading('Edit method currently in use?')
                ->modalDescription(fn(AnonymizationMethods $record) => ($record->isInUse()
                    ? 'This method is attached to jobs/columns. Editing it can change generated anonymization SQL. You can also create a new version instead.'
                    : ''))
                ->modalSubmitActionLabel('Continue to edit')
                ->form(fn(AnonymizationMethods $record) => $record->isInUse()
                    ? [
                        Checkbox::make('acknowledge')
                            ->label('I understand and want to continue.')
                            ->accepted()
                            ->required(),
                    ]
                    : [])
                ->action(fn() => $this->redirect(AnonymizationMethodResource::getUrl('edit', ['record' => $this->getRecord()]))),
        ];
    }
}
