<?php

namespace App\Filament\Fodig\Resources\SiebelApplicationResource\Pages;

use App\Filament\Fodig\Resources\SiebelApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelApplication extends EditRecord
{
    protected static string $resource = SiebelApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
