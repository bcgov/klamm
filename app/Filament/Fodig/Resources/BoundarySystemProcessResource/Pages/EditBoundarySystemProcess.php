<?php

namespace App\Filament\Fodig\Resources\BoundarySystemProcessResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemProcess extends EditRecord
{
    protected static string $resource = BoundarySystemProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
