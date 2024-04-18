<?php

namespace App\Filament\Resources\BusinessFormGroupResource\Pages;

use App\Filament\Resources\BusinessFormGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusinessFormGroup extends EditRecord
{
    protected static string $resource = BusinessFormGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
