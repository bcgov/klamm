<?php

namespace App\Filament\Fodig\Resources\BoundarySystemResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystem extends EditRecord
{
    protected static string $resource = BoundarySystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
