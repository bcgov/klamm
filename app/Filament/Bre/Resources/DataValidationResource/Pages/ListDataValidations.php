<?php

namespace App\Filament\Bre\Resources\DataValidationResource\Pages;

use App\Filament\Bre\Resources\DataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataValidations extends ListRecords
{
    protected static string $resource = DataValidationResource::class;
    protected static ?string $title = 'BRE Field Data Validations';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Field Data Validation')),
        ];
    }
}
