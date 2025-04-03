<?php

namespace App\Filament\Fodig\Resources\SiebelFieldResource\Pages;

use App\Filament\Fodig\Resources\SiebelFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelField extends EditRecord
{
    protected static string $resource = SiebelFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
