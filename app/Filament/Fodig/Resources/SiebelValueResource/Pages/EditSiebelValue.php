<?php

namespace App\Filament\Fodig\Resources\SiebelValueResource\Pages;

use App\Filament\Fodig\Resources\SiebelValueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelValue extends EditRecord
{
    protected static string $resource = SiebelValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
