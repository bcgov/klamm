<?php

namespace App\Filament\Forms\Resources\DataBindingMappingResource\Pages;

use App\Filament\Forms\Resources\DataBindingMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDataBindingMapping extends ViewRecord
{
    protected static string $resource = DataBindingMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
