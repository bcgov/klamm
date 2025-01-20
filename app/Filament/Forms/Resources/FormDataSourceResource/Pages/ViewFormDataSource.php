<?php

namespace App\Filament\Forms\Resources\FormDataSourceResource\Pages;

use App\Filament\Forms\Resources\FormDataSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormDataSource extends ViewRecord
{
    protected static string $resource = FormDataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
