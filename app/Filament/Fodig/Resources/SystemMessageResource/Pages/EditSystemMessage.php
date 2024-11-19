<?php

namespace App\Filament\Fodig\Resources\SystemMessageResource\Pages;

use App\Filament\Fodig\Resources\SystemMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystemMessage extends EditRecord
{
    protected static string $resource = SystemMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
