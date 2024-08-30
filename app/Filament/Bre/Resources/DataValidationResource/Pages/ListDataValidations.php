<?php

namespace App\Filament\Bre\Resources\DataValidationResource\Pages;

use App\Filament\Bre\Resources\DataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataValidations extends ListRecords
{
    protected static string $resource = DataValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
