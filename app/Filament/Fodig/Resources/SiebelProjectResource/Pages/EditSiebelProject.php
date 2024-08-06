<?php

namespace App\Filament\Fodig\Resources\SiebelProjectResource\Pages;

use App\Filament\Fodig\Resources\SiebelProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelProject extends EditRecord
{
    protected static string $resource = SiebelProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
