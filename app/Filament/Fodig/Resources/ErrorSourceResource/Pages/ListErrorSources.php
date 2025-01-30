<?php

namespace App\Filament\Fodig\Resources\ErrorSourceResource\Pages;

use App\Filament\Fodig\Resources\ErrorSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListErrorSources extends ListRecords
{
    protected static string $resource = ErrorSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
