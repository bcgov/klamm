<?php

namespace App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationPackageResource;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationPackage extends EditRecord
{
    protected static string $resource = AnonymizationPackageResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Package updated';
    }
}
