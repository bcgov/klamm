<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use App\Filament\Fodig\Resources\AnonymizationPackageResource;
use App\Models\Anonymizer\AnonymizationPackage;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymizationMethod extends CreateRecord
{
    protected static string $resource = AnonymizationMethodResource::class;

    // After creating a new anonymization method, check if we need to attach it to a package.
    // This is necessary to support the "attach method" flow from within package editing.

    protected function afterCreate(): void
    {
        $packageId = (int) request()->integer('attach_package_id');

        if ($packageId <= 0 || ! $this->record) {
            return;
        }

        $package = AnonymizationPackage::query()->find($packageId);

        if (! $package) {
            return;
        }

        $package->methods()->syncWithoutDetaching([$this->record->getKey()]);
    }


    // If created from package view, redirect back to package page.
    // Otherwise, fall back to the normal resource view page for the new method.

    protected function getRedirectUrl(): string
    {
        $returnTo = request()->query('return_to');

        if (is_string($returnTo) && $returnTo !== '') {
            return $returnTo;
        }

        $packageId = (int) request()->integer('attach_package_id');
        if ($packageId > 0) {
            return AnonymizationPackageResource::getUrl('edit', ['record' => $packageId]);
        }

        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Anonymization method created';
    }
}
