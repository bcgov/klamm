<?php

namespace App\Filament\Forms\Resources\FormFieldDataBindingResource\Pages;

use App\Filament\Forms\Resources\FormFieldDataBindingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormFieldDataBinding extends ViewRecord
{
    protected static string $resource = FormFieldDataBindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
