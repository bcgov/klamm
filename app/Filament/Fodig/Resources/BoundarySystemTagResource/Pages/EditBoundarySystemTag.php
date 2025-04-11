<?php

namespace App\Filament\Fodig\Resources\BoundarySystemTagResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemTagResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemTag extends EditRecord
{
    protected static string $resource = BoundarySystemTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
