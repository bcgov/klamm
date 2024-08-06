<?php

namespace App\Filament\Fodig\Resources\SiebelAppletResource\Pages;

use App\Filament\Fodig\Resources\SiebelAppletResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelApplet extends EditRecord
{
    protected static string $resource = SiebelAppletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
