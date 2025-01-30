<?php

namespace App\Filament\Fodig\Resources\ErrorDataGroupResource\Pages;

use App\Filament\Fodig\Resources\ErrorDataGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewErrorDataGroup extends ViewRecord
{
    protected static string $resource = ErrorDataGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
