<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFieldResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFileField extends EditRecord
{
    protected static string $resource = BoundarySystemFileFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
