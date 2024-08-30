<?php

namespace App\Filament\Bre\Resources\ValidationTypeResource\Pages;

use App\Filament\Bre\Resources\ValidationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListValidationTypes extends ListRecords
{
    protected static string $resource = ValidationTypeResource::class;
    protected static ?string $title = 'BRE Validation Types';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Validation Type')),
        ];
    }
}
