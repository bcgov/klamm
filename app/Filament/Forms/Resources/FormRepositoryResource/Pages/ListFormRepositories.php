<?php

namespace App\Filament\Forms\Resources\FormRepositoryResource\Pages;

use App\Filament\Forms\Resources\FormRepositoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormRepositories extends ListRecords
{
    protected static string $resource = FormRepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
