<?php

namespace App\Filament\Bre\Resources\ICMCDWFieldResource\Pages;

use App\Filament\Bre\Resources\ICMCDWFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditICMCDWField extends EditRecord
{
    protected static string $resource = ICMCDWFieldResource::class;
    protected static ?string $title = 'Edit ICM CDW Field';
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
