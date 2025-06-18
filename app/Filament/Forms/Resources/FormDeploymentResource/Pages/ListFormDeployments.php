<?php

namespace App\Filament\Forms\Resources\FormDeploymentResource\Pages;

use App\Filament\Forms\Resources\FormDeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormDeployments extends ListRecords
{
    protected static string $resource = FormDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
