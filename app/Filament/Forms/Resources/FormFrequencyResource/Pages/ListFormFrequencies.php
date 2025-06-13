<?php

namespace App\Filament\Forms\Resources\FormFrequencyResource\Pages;

use App\Filament\Forms\Resources\FormFrequencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormFrequencies extends ListRecords
{
    protected static string $resource = FormFrequencyResource::class;

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
