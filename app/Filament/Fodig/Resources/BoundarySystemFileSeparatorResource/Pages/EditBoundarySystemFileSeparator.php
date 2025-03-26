<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileSeparatorResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileSeparatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFileSeparator extends EditRecord
{
    protected static string $resource = BoundarySystemFileSeparatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
