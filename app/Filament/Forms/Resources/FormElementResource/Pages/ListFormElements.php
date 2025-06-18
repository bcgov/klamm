<?php

namespace App\Filament\Forms\Resources\FormElementResource\Pages;

use App\Filament\Forms\Resources\FormElementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormElements extends ListRecords
{
    protected static string $resource = FormElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
