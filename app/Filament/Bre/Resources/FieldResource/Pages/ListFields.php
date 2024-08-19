<?php

namespace App\Filament\Bre\Resources\FieldResource\Pages;

use App\Filament\Bre\Resources\FieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFields extends ListRecords
{
    protected static string $resource = FieldResource::class;
    protected static ?string $title = 'BRE Rule Fields';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Rule Field')),
        ];
    }
}
