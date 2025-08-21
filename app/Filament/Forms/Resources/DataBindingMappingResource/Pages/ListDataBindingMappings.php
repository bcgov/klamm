<?php

namespace App\Filament\Forms\Resources\DataBindingMappingResource\Pages;

use App\Filament\Forms\Resources\DataBindingMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataBindingMappings extends ListRecords
{
    protected static string $resource = DataBindingMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Databinding Mapping')
                ->icon('heroicon-m-plus')
                ->authorize(fn () => DataBindingMappingResource::canCreate()),
        ];
    }
}
