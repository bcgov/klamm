<?php

namespace App\Filament\Admin\Resources\MinistryResource\Pages;

use App\Filament\Admin\Resources\MinistryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMinistry extends EditRecord
{
    protected static string $resource = MinistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
