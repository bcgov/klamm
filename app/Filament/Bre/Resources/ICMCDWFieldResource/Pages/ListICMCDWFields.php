<?php

namespace App\Filament\Bre\Resources\ICMCDWFieldResource\Pages;

use App\Filament\Bre\Resources\ICMCDWFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListICMCDWFields extends ListRecords
{
    protected static string $resource = ICMCDWFieldResource::class;
    protected static ?string $title = 'ICM CDW Fields';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New ICM CDW Field')),
        ];
    }
}
