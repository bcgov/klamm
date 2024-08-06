<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessComponentResource\Pages;

use App\Filament\Fodig\Resources\SiebelBusinessComponentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelBusinessComponent extends EditRecord
{
    protected static string $resource = SiebelBusinessComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
