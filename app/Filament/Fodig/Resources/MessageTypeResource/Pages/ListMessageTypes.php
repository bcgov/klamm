<?php

namespace App\Filament\Fodig\Resources\MessageTypeResource\Pages;

use App\Filament\Fodig\Resources\MessageTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMessageTypes extends ListRecords
{
    protected static string $resource = MessageTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
