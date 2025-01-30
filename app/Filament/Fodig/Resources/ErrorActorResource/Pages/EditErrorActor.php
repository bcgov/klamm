<?php

namespace App\Filament\Fodig\Resources\ErrorActorResource\Pages;

use App\Filament\Fodig\Resources\ErrorActorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorActor extends EditRecord
{
    protected static string $resource = ErrorActorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
