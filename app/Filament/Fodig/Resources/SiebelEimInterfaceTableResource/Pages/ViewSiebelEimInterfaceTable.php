<?php

namespace App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource\Pages;

use App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelEimInterfaceTable extends ViewRecord
{
    protected static string $resource = SiebelEimInterfaceTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
