<?php

namespace App\Filament\Fodig\Resources\SiebelScreenResource\Pages;

use App\Filament\Fodig\Resources\SiebelScreenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelScreen extends EditRecord
{
    protected static string $resource = SiebelScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
