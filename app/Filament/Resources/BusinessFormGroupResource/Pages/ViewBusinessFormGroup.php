<?php

namespace App\Filament\Resources\BusinessFormGroupResource\Pages;

use App\Filament\Resources\BusinessFormGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessFormGroup extends ViewRecord
{
    protected static string $resource = BusinessFormGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
