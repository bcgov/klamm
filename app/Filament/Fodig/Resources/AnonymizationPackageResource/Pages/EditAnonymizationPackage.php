<?php

namespace App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationPackageResource;
use App\Models\Anonymizer\AnonymizationPackage;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationPackage extends EditRecord
{
    protected static string $resource = AnonymizationPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_version')
                ->label('New version')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->modalHeading('Create a new package version?')
                ->modalDescription('This will duplicate the package settings into a new record. It will not be attached to any methods automatically.')
                ->modalSubmitActionLabel('Create version')
                ->action(function () {
                    /** @var AnonymizationPackage $record */
                    $record = $this->getRecord();
                    $new = $record->createNewVersion();

                    return $this->redirect(AnonymizationPackageResource::getUrl('edit', ['record' => $new]));
                }),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading(fn(AnonymizationPackage $record) => $record->isInUse() ? 'Delete package currently in use?' : 'Delete package?')
                ->modalDescription(fn(AnonymizationPackage $record) => $record->isInUse()
                    ? 'This package is attached to methods used by jobs/columns. Deleting it can break future SQL regeneration.'
                    : 'This will soft-delete the anonymization package.')
                ->form(fn(AnonymizationPackage $record) => $record->isInUse()
                    ? [
                        Checkbox::make('acknowledge')
                            ->label('I understand and want to delete this package anyway.')
                            ->accepted()
                            ->required(),
                    ]
                    : []),
        ];
    }

    protected function getFormActions(): array
    {
        /** @var AnonymizationPackage $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('save')
                ->label('Save changes')
                ->submit('save')
                ->keyBindings(['mod+s'])
                ->requiresConfirmation(fn() => $record->isInUse())
                ->modalHeading(fn() => $record->isInUse() ? 'Save changes to package currently in use?' : null)
                ->modalDescription(fn() => $record->isInUse()
                    ? 'This package is attached to methods used by jobs/columns. Saving changes can alter generated anonymization SQL.'
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
                ->url(fn() => AnonymizationPackageResource::getUrl('view', ['record' => $record])),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Package updated';
    }
}
