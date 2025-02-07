<?php

namespace App\Filament\Forms\Resources\StyleResource\Pages;

use App\Filament\Forms\Resources\StyleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStyle extends ViewRecord
{
    protected static string $resource = StyleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
