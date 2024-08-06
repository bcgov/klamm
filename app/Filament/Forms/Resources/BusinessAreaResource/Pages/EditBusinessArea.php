<?php

namespace App\Filament\Forms\Resources\BusinessAreaResource\Pages;

use App\Filament\Forms\Resources\BusinessAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusinessArea extends EditRecord
{
    protected static string $resource = BusinessAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
