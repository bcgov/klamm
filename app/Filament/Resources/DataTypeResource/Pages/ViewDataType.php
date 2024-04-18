<?php

namespace App\Filament\Resources\DataTypeResource\Pages;

use App\Filament\Resources\DataTypeResource;
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
