<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStyleSheets extends ListRecords
{
    protected static string $resource = StyleSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
