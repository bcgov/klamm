<?php

namespace App\Filament\Forms\Resources\FormInterfaceResource\Pages;

use App\Filament\Forms\Resources\FormInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormInterface extends EditRecord
{
    protected static string $resource = FormInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
