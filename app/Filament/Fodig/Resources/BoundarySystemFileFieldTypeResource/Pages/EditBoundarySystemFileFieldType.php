<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFileFieldType extends EditRecord
{
    protected static string $resource = BoundarySystemFileFieldTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
