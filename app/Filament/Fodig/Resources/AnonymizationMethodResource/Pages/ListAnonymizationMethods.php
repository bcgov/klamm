<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymizationMethods extends ListRecords
{
    protected static string $resource = AnonymizationMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Method')
                ->icon('heroicon-o-plus-small'),
        ];
    }
}
