<?php

namespace App\Filament\Forms\Resources\BusinessAreaResource\Pages;

use App\Filament\Forms\Resources\BusinessAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListBusinessAreas extends ListRecords
{
    protected static string $resource = BusinessAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
