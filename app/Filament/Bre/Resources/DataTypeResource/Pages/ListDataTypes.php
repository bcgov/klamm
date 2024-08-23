<?php

namespace App\Filament\Bre\Resources\DataTypeResource\Pages;

use App\Filament\Bre\Resources\DataTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataTypes extends ListRecords
{
    protected static string $resource = DataTypeResource::class;
    protected static ?string $title = 'BRE Rule Field Data Value Types';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Data Value Type')),
        ];
    }
}
