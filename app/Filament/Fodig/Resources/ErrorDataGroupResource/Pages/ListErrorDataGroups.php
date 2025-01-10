<?php

namespace App\Filament\Fodig\Resources\ErrorDataGroupResource\Pages;

use App\Filament\Fodig\Resources\ErrorDataGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListErrorDataGroups extends ListRecords
{
    protected static string $resource = ErrorDataGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
