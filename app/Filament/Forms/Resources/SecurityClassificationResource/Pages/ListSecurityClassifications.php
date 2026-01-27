<?php

namespace App\Filament\Forms\Resources\SecurityClassificationResource\Pages;

use App\Filament\Forms\Resources\SecurityClassificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSecurityClassifications extends ListRecords
{
    protected static string $resource = SecurityClassificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
