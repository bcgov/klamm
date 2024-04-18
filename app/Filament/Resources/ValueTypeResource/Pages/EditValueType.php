<?php

namespace App\Filament\Resources\ValueTypeResource\Pages;

use App\Filament\Resources\ValueTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditValueType extends EditRecord
{
    protected static string $resource = ValueTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
