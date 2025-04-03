<?php

namespace App\Filament\Fodig\Resources\BoundarySystemFrequencyResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemFrequencyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemFrequency extends EditRecord
{
    protected static string $resource = BoundarySystemFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
