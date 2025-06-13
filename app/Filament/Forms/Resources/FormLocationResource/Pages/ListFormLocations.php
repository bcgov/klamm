<?php

namespace App\Filament\Forms\Resources\FormLocationResource\Pages;

use App\Filament\Forms\Resources\FormLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormLocations extends ListRecords
{
    protected static string $resource = FormLocationResource::class;

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
