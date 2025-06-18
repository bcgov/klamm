<?php

namespace App\Filament\Forms\Resources\FormStylesheetResource\Pages;

use App\Filament\Forms\Resources\FormStylesheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormStylesheet extends ViewRecord
{
    protected static string $resource = FormStylesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
