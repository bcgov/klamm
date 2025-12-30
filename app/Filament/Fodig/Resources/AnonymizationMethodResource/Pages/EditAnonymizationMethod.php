<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use App\Models\Anonymizer\AnonymizationMethods;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationMethod extends EditRecord
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
                ->action(function () {
                    /** @var AnonymizationMethods $record */
                    $record = $this->getRecord();
                    $new = $record->createNewVersion();

                    return $this->redirect(AnonymizationMethodResource::getUrl('edit', ['record' => $new]));
                }),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading(fn(AnonymizationMethods $record) => $record->isInUse() ? 'Delete method currently in use?' : 'Delete method?')
                ->modalDescription(fn(AnonymizationMethods $record) => $record->isInUse()
                    ? 'This method is attached to jobs/columns. Deleting it can break future SQL regeneration and remove it from selection.'
                    : 'This will soft-delete the anonymization method.')
                ->form(fn(AnonymizationMethods $record) => $record->isInUse()
                    ? [
                        Checkbox::make('acknowledge')
                            ->label('I understand and want to delete this method anyway.')
                            ->accepted()
                            ->required(),
                    ]
                    : []),
        ];
    }

    protected function getFormActions(): array
    {
        /** @var AnonymizationMethods $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('save')
                ->label('Save changes')
                ->submit('save')
                ->keyBindings(['mod+s'])
                ->requiresConfirmation(fn() => $record->isInUse())
                ->modalHeading(fn() => $record->isInUse() ? 'Save changes to method currently in use?' : null)
                ->modalDescription(fn() => $record->isInUse()
                    ? 'This method is attached to jobs/columns. Saving changes can alter generated anonymization SQL.'
                    : null)
                ->modalSubmitActionLabel('Save anyway')
                ->form(fn() => $record->isInUse()
                    ? [
                        Checkbox::make('acknowledge')
                            ->label('I understand and want to save changes anyway.')
                            ->accepted()
                            ->required(),
                    ]
                    : []),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn() => AnonymizationMethodResource::getUrl('view', ['record' => $record])),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Anonymization method updated';
    }
}
