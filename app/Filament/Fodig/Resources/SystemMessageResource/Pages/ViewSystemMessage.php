<?php

namespace App\Filament\Fodig\Resources\SystemMessageResource\Pages;

use App\Filament\Fodig\Resources\SystemMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSystemMessage extends ViewRecord
{
    protected static string $resource = SystemMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
