<?php

namespace App\Filament\Fodig\Resources\ICMErrorMessageResource\Pages;

use App\Filament\Fodig\Resources\ICMErrorMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditICMErrorMessage extends EditRecord
{
    protected static string $resource = ICMErrorMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
