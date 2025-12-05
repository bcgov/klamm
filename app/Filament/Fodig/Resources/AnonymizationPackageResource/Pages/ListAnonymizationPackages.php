<?php

namespace App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymizationPackages extends ListRecords
{
    protected static string $resource = AnonymizationPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Package')
                ->icon('heroicon-o-plus-small'),
        ];
    }
}
