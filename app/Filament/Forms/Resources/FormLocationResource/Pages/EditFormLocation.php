<?php

namespace App\Filament\Forms\Resources\FormLocationResource\Pages;

use App\Filament\Forms\Resources\FormLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormLocation extends EditRecord
{
    protected static string $resource = FormLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
