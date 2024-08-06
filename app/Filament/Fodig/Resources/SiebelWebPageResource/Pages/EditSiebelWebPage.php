<?php

namespace App\Filament\Fodig\Resources\SiebelWebPageResource\Pages;

use App\Filament\Fodig\Resources\SiebelWebPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelWebPage extends EditRecord
{
    protected static string $resource = SiebelWebPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
