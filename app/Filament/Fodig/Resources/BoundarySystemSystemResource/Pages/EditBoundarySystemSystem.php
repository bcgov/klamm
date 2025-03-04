<?php

namespace App\Filament\Fodig\Resources\BoundarySystemSystemResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemSystemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemSystem extends EditRecord
{
    protected static string $resource = BoundarySystemSystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
