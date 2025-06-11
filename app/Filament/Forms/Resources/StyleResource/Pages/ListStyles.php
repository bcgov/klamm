<?php

namespace App\Filament\Forms\Resources\StyleResource\Pages;

use App\Filament\Forms\Resources\StyleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStyles extends ListRecords
{
    protected static string $resource = StyleResource::class;

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
