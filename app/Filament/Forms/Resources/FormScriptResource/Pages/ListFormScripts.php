<?php

namespace App\Filament\Forms\Resources\FormScriptResource\Pages;

use App\Filament\Forms\Resources\FormScriptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormScripts extends ListRecords
{
    protected static string $resource = FormScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
