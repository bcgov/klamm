<?php

namespace App\Filament\Bre\Resources\ValueTypeResource\Pages;

use App\Filament\Bre\Resources\ValueTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditValueType extends EditRecord
{
    protected static string $resource = ValueTypeResource::class;
    protected static ?string $title = 'Edit Field Value Type';
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
