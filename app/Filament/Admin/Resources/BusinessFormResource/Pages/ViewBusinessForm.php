<?php

namespace App\Filament\Admin\Resources\BusinessFormResource\Pages;

use App\Filament\Admin\Resources\BusinessFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessForm extends ViewRecord
{
    protected static string $resource = BusinessFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
