<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFileFieldMapSection extends EditRecord
{
    protected static string $resource = BoundarySystemFileFieldMapSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
