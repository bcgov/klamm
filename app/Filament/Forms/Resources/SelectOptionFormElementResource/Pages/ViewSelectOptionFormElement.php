<?php

namespace App\Filament\Forms\Resources\SelectOptionFormElementResource\Pages;

use App\Filament\Forms\Resources\SelectOptionFormElementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSelectOptionFormElement extends ViewRecord
{
    protected static string $resource = SelectOptionFormElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
