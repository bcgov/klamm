<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormVersion extends ViewRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
