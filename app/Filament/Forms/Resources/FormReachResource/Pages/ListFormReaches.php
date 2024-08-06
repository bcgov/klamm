<?php

namespace App\Filament\Forms\Resources\FormReachResource\Pages;

use App\Filament\Forms\Resources\FormReachResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormReaches extends ListRecords
{
    protected static string $resource = FormReachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
