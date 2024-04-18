<?php

namespace App\Filament\Resources\SelectOptionsResource\Pages;

use App\Filament\Resources\SelectOptionsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSelectOptions extends ViewRecord
{
    protected static string $resource = SelectOptionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
