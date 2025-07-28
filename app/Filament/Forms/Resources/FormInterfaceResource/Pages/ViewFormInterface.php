<?php

namespace App\Filament\Forms\Resources\FormInterfaceResource\Pages;

use App\Filament\Forms\Resources\FormInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormInterface extends ViewRecord
{
    protected static string $resource = FormInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
