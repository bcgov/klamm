<?php

namespace App\Filament\Forms\Resources\UserTypeResource\Pages;

use App\Filament\Forms\Resources\UserTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserTypes extends ListRecords
{
    protected static string $resource = UserTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
