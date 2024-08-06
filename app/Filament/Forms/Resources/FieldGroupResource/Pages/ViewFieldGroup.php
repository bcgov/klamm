<?php

namespace App\Filament\Forms\Resources\FieldGroupResource\Pages;

use App\Filament\Forms\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFieldGroup extends ViewRecord
{
    protected static string $resource = FieldGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
