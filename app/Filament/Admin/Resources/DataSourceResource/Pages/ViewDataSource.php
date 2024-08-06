<?php

namespace App\Filament\Admin\Resources\DataSourceResource\Pages;

use App\Filament\Admin\Resources\DataSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDataSource extends ViewRecord
{
    protected static string $resource = DataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
