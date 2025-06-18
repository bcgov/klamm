<?php

namespace App\Filament\Forms\Resources\FormFieldDataBindingResource\Pages;

use App\Filament\Forms\Resources\FormFieldDataBindingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormFieldDataBindings extends ListRecords
{
    protected static string $resource = FormFieldDataBindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
