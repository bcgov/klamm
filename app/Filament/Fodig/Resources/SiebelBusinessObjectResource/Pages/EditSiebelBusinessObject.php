<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessObjectResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessObjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelBusinessObject extends EditRecord
{
    protected static string $resource = SiebelBusinessObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
