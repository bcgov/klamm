<?php

namespace App\Filament\Forms\Resources\DataTypeResource\Pages;

use App\Filament\Forms\Resources\DataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataTypes extends ListRecords
{
    protected static string $resource = DataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }
}
