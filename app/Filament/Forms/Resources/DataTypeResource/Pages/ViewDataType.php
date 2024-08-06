<?php

namespace App\Filament\Forms\Resources\DataTypeResource\Pages;

use App\Filament\Forms\Resources\DataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDataType extends ViewRecord
{
    protected static string $resource = DataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
