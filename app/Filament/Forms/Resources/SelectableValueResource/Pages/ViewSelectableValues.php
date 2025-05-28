<?php

namespace App\Filament\Forms\Resources\SelectableValueResource\Pages;

use App\Filament\Forms\Resources\SelectableValueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSelectableValue extends ViewRecord
{
    protected static string $resource = SelectableValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
