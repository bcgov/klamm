<?php

namespace App\Filament\Fodig\Resources\ErrorSourceResource\Pages;

use App\Filament\Fodig\Resources\ErrorSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorSource extends EditRecord
{
    protected static string $resource = ErrorSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
