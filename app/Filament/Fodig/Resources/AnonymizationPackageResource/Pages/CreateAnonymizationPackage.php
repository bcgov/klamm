<?php

namespace App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationPackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymizationPackage extends CreateRecord
{
    protected static string $resource = AnonymizationPackageResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Package saved';
    }
}
