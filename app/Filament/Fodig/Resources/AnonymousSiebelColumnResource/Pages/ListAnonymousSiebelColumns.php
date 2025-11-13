<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousSiebelColumns extends ListRecords
{
    protected static string $resource = AnonymousSiebelColumnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
