<?php

namespace App\Filament\Fodig\Resources\DataGroupResource\Pages;

use App\Filament\Fodig\Resources\DataGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataGroup extends EditRecord
{
    protected static string $resource = DataGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
