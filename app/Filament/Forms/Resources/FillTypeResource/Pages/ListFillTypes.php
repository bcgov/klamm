<?php

namespace App\Filament\Forms\Resources\FillTypeResource\Pages;

use App\Filament\Forms\Resources\FillTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFillTypes extends ListRecords
{
    protected static string $resource = FillTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
