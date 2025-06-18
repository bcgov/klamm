<?php

namespace App\Filament\Forms\Resources\FormDeploymentResource\Pages;

use App\Filament\Forms\Resources\FormDeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormDeployment extends EditRecord
{
    protected static string $resource = FormDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
