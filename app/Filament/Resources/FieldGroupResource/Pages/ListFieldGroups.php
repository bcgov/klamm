<?php

namespace App\Filament\Resources\FieldGroupResource\Pages;

use App\Filament\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFieldGroups extends ListRecords
{
    protected static string $resource = FieldGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
