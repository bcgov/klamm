<?php

namespace App\Filament\Fodig\Resources\SiebelTableResource\Pages;

use App\Filament\Fodig\Resources\SiebelTableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelTable extends EditRecord
{
    protected static string $resource = SiebelTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
