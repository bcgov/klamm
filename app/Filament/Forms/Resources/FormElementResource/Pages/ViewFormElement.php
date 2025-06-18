<?php

namespace App\Filament\Forms\Resources\FormElementResource\Pages;

use App\Filament\Forms\Resources\FormElementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormElement extends ViewRecord
{
    protected static string $resource = FormElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
