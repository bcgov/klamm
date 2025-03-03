<?php

namespace App\Filament\Forms\Resources\StyleResource\Pages;

use App\Filament\Forms\Resources\StyleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStyle extends EditRecord
{
    protected static string $resource = StyleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
