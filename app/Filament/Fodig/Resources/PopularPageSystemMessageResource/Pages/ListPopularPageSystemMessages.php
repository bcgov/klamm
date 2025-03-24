<?php

namespace App\Filament\Fodig\Resources\PopularPageSystemMessageResource\Pages;

use App\Filament\Fodig\Resources\PopularPageSystemMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\PopularPageSystemMessage;


class ListPopularPageSystemMessages extends ListRecords
{
    protected static string $resource = PopularPageSystemMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn() => !PopularPageSystemMessage::hasReachedMaximum()),
        ];
    }
}
