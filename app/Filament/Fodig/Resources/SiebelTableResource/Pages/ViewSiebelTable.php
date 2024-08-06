<?php

namespace App\Filament\Fodig\Resources\SiebelTableResource\Pages;

use App\Filament\Fodig\Resources\SiebelTableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelTable extends ViewRecord
{
    protected static string $resource = SiebelTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
