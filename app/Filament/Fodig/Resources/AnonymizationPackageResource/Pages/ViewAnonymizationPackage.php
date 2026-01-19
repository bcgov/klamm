<?php

namespace App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationPackageResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use App\Models\Anonymizer\AnonymizationPackage;
use Filament\Forms\Components\Checkbox;


class ViewAnonymizationPackage extends ViewRecord
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
                ->action(function (AnonymizationPackage $record) {
                    $new = $record->createNewVersion();

                    return $this->redirect(AnonymizationPackageResource::getUrl('edit', ['record' => $new]));
                }),
            Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->requiresConfirmation(fn(AnonymizationPackage $record) => $record->isInUse())
                ->modalHeading('Edit package currently in use?')
                ->modalDescription(fn(AnonymizationPackage $record) => ($record->isInUse()
                    ? 'This package is attached to methods used by jobs/columns. Editing it can change generated anonymization SQL. You can also create a new version instead.'
                    : ''))
                ->modalSubmitActionLabel('Continue to edit')
                ->form(fn(AnonymizationPackage $record) => $record->isInUse()
                    ? [
                        Checkbox::make('acknowledge')
                            ->label('I understand and want to continue.')
                            ->accepted()
                            ->required(),
                    ]
                    : [])
                ->action(fn() => $this->redirect(AnonymizationPackageResource::getUrl('edit', ['record' => $this->getRecord()]))),
        ];
    }
}
