<?php

namespace App\Filament\Admin\Resources\BusinessFormResource\Pages;

use App\Filament\Admin\Resources\BusinessFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinessForms extends ListRecords
{
    protected static string $resource = BusinessFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
