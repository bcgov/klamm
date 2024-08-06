<?php

namespace App\Filament\Admin\Resources\BusinessFormGroupResource\Pages;

use App\Filament\Admin\Resources\BusinessFormGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinessFormGroups extends ListRecords
{
    protected static string $resource = BusinessFormGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
