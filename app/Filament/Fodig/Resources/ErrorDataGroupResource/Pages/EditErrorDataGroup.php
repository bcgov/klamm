<?php

namespace App\Filament\Fodig\Resources\ErrorDataGroupResource\Pages;

use App\Filament\Fodig\Resources\ErrorDataGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorDataGroup extends EditRecord
{
    protected static string $resource = ErrorDataGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
