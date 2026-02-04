<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymizationJobs extends ListRecords
{
    protected static string $resource = AnonymizationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Job')
                ->icon('heroicon-o-plus-small'),
        ];
    }
}
