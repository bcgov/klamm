<?php

namespace App\Filament\Forms\Resources\FormDataSourceResource\Pages;

use App\Filament\Forms\Resources\FormDataSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormDataSources extends ListRecords
{
    protected static string $resource = FormDataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }
}
