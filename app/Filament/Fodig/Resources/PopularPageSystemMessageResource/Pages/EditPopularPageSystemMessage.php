<?php

namespace App\Filament\Fodig\Resources\PopularPageSystemMessageResource\Pages;

use App\Filament\Fodig\Resources\PopularPageSystemMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPopularPageSystemMessage extends EditRecord
{
    protected static string $resource = PopularPageSystemMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
