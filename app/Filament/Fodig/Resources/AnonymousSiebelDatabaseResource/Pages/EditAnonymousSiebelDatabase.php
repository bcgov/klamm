<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymousSiebelDatabase extends EditRecord
{
    protected static string $resource = AnonymousSiebelDatabaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
