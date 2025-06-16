<?php

namespace App\Filament\Forms\Resources\FormSoftwareSourceResource\Pages;

use App\Filament\Forms\Resources\FormSoftwareSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormSoftwareSources extends ListRecords
{
    protected static string $resource = FormSoftwareSourceResource::class;

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
