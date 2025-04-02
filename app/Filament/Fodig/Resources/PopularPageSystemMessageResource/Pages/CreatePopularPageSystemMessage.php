<?php

namespace App\Filament\Fodig\Resources\PopularPageSystemMessageResource\Pages;

use App\Filament\Fodig\Resources\PopularPageSystemMessageResource;
use App\Models\PopularPageSystemMessage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePopularPageSystemMessage extends CreateRecord
{
    protected static string $resource = PopularPageSystemMessageResource::class;

    protected function beforeCreate(): void
    {
        if (PopularPageSystemMessage::hasReachedMaximum()) {
            Notification::make()
                ->title('Cannot add more than 4 popular pages')
                ->danger()
                ->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
