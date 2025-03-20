<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFileFieldMap extends EditRecord
{
    protected static string $resource = BoundarySystemFileFieldMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
