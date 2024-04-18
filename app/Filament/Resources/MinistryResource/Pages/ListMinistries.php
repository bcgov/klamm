<?php

namespace App\Filament\Resources\MinistryResource\Pages;

use App\Filament\Resources\MinistryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMinistries extends ListRecords
{
    protected static string $resource = MinistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
