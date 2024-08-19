<?php

namespace App\Filament\Bre\Resources\FieldResource\Pages;

use App\Filament\Bre\Resources\FieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewField extends ViewRecord
{
    protected static string $resource = FieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
