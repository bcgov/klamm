<?php

namespace App\Filament\Admin\Resources\MinistryResource\Pages;

use App\Filament\Admin\Resources\MinistryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMinistry extends ViewRecord
{
    protected static string $resource = MinistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
