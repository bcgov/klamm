<?php

namespace App\Filament\Bre\Resources\ICMCDWFieldResource\Pages;

use App\Filament\Bre\Resources\ICMCDWFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewICMCDWField extends ViewRecord
{
    protected static string $resource = ICMCDWFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
