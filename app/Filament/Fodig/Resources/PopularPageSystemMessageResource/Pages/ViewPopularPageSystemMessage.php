<?php

namespace App\Filament\Fodig\Resources\PopularPageSystemMessageResource\Pages;

use App\Filament\Fodig\Resources\PopularPageSystemMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPopularPageSystemMessage extends ViewRecord
{
    protected static string $resource = PopularPageSystemMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
