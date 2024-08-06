<?php

namespace App\Filament\Admin\Resources\DataSourceResource\Pages;

use App\Filament\Admin\Resources\DataSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataSources extends ListRecords
{
    protected static string $resource = DataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
