<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFile extends EditRecord
{
    protected static string $resource = BoundarySystemFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
