<?php

namespace App\Filament\Fodig\Resources\SiebelViewResource\Pages;

use App\Filament\Fodig\Resources\SiebelViewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelView extends EditRecord
{
    protected static string $resource = SiebelViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
