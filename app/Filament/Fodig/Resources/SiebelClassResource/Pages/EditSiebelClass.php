<?php

namespace App\Filament\Fodig\Resources\SiebelClassResource\Pages;

use App\Filament\Fodig\Resources\SiebelClassResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelClass extends EditRecord
{
    protected static string $resource = SiebelClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
