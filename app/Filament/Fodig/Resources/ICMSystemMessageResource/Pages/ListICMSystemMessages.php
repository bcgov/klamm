<?php

namespace App\Filament\Fodig\Resources\ICMSystemMessageResource\Pages;

use App\Filament\Fodig\Resources\ICMSystemMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListICMSystemMessages extends ListRecords
{
    protected static string $resource = ICMSystemMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
