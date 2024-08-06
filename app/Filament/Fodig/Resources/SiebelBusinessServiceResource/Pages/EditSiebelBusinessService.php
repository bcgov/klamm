<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessServiceResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelBusinessService extends EditRecord
{
    protected static string $resource = SiebelBusinessServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
