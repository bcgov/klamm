<?php

namespace App\Filament\Bre\Resources\ValidationTypeResource\Pages;

use App\Filament\Bre\Resources\ValidationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewValidationType extends ViewRecord
{
    protected static string $resource = ValidationTypeResource::class;
    protected static ?string $title = 'View BRE Validation Type';
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
