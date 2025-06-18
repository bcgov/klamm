<?php

namespace App\Filament\Forms\Resources\FormDeploymentResource\Pages;

use App\Filament\Forms\Resources\FormDeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormDeployment extends ViewRecord
{
    protected static string $resource = FormDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
