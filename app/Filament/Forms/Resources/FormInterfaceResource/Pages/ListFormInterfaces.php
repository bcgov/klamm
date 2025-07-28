<?php

namespace App\Filament\Forms\Resources\FormInterfaceResource\Pages;

use App\Filament\Forms\Resources\FormInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormInterfaces extends ListRecords
{
    protected static string $resource = FormInterfaceResource::class;

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
