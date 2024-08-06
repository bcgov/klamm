<?php

namespace App\Filament\Forms\Resources\FormSoftwareSourceResource\Pages;

use App\Filament\Forms\Resources\FormSoftwareSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormSoftwareSource extends EditRecord
{
    protected static string $resource = FormSoftwareSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
