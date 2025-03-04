<?php

namespace App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemModeOfTransfer extends EditRecord
{
    protected static string $resource = BoundarySystemModeOfTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
