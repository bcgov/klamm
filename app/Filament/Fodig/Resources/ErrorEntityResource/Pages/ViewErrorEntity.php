<?php

namespace App\Filament\Fodig\Resources\ErrorEntityResource\Pages;

use App\Filament\Fodig\Resources\ErrorEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewErrorEntity extends ViewRecord
{
    protected static string $resource = ErrorEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
