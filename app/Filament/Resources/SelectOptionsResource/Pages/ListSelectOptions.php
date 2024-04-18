<?php

namespace App\Filament\Resources\SelectOptionsResource\Pages;

use App\Filament\Resources\SelectOptionsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSelectOptions extends ListRecords
{
    protected static string $resource = SelectOptionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
