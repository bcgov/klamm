<?php

namespace App\Filament\Bre\Resources\FieldGroupResource\Pages;

use App\Filament\Bre\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFieldGroups extends ListRecords
{
    protected static string $resource = FieldGroupResource::class;
    protected static ?string $title = 'BRE Rule Field Groups';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Rule Field Group')),
        ];
    }
}
