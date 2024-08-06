<?php

namespace App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource\Pages;

use App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelEimInterfaceTable extends EditRecord
{
    protected static string $resource = SiebelEimInterfaceTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
