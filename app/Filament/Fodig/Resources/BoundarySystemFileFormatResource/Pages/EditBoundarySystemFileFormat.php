<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFileFormatResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFileFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFileFormat extends EditRecord
{
    protected static string $resource = BoundarySystemFileFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
