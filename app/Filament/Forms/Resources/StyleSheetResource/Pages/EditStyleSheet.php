<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStyleSheet extends EditRecord
{
    protected static string $resource = StyleSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
