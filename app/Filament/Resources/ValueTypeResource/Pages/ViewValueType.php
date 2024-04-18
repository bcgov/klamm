<?php

namespace App\Filament\Resources\ValueTypeResource\Pages;

use App\Filament\Resources\ValueTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewValueType extends ViewRecord
{
    protected static string $resource = ValueTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
