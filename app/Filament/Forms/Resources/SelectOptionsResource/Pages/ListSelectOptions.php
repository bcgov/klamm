<?php

namespace App\Filament\Forms\Resources\SelectOptionsResource\Pages;

use App\Filament\Forms\Resources\SelectOptionsResource;
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
