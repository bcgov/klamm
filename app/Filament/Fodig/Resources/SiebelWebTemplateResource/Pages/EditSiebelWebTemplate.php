<?php

namespace App\Filament\Fodig\Resources\SiebelWebTemplateResource\Pages;

use App\Filament\Fodig\Resources\SiebelWebTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelWebTemplate extends EditRecord
{
    protected static string $resource = SiebelWebTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
