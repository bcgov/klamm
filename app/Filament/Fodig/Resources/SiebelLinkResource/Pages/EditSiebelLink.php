<?php

namespace App\Filament\Fodig\Resources\SiebelLinkResource\Pages;

use App\Filament\Fodig\Resources\SiebelLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelLink extends EditRecord
{
    protected static string $resource = SiebelLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
