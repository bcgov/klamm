<?php

namespace App\Filament\Fodig\Resources\BoundarySystemInterfaceResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoundarySystemInterface extends EditRecord
{
    protected static string $resource = BoundarySystemInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }
}
