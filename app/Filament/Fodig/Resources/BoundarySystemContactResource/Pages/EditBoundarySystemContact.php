<?php

namespace App\Filament\Fodig\Resources\BoundarySystemContactResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemContactResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemContact extends EditRecord
{
    protected static string $resource = BoundarySystemContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
