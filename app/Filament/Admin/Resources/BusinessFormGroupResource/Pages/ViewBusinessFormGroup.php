<?php

namespace App\Filament\Admin\Resources\BusinessFormGroupResource\Pages;

use App\Filament\Admin\Resources\BusinessFormGroupResource;
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
