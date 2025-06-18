<?php

namespace App\Filament\Forms\Resources\SelectOptionFormElementResource\Pages;

use App\Filament\Forms\Resources\SelectOptionFormElementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSelectOptionFormElements extends ListRecords
{
    protected static string $resource = SelectOptionFormElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
