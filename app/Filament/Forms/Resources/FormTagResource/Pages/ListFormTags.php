<?php

namespace App\Filament\Forms\Resources\FormTagResource\Pages;

use App\Filament\Forms\Resources\FormTagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormTags extends ListRecords
{
    protected static string $resource = FormTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
