<?php

namespace App\Filament\Bre\Resources\ValueTypeResource\Pages;

use App\Filament\Bre\Resources\ValueTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListValueTypes extends ListRecords
{
    protected static string $resource = ValueTypeResource::class;
    protected static ?string $title = 'Field Value Types';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Field Value Type')),
        ];
    }
}
