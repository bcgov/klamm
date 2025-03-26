<?php

namespace App\Filament\Fodig\Resources\ErrorEntityResource\Pages;

use App\Filament\Fodig\Resources\ErrorEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorEntity extends EditRecord
{
    protected static string $resource = ErrorEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
