<?php

namespace App\Filament\Fodig\Resources\AnonymizationRuleResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationRuleResource;
use Filament\Resources\Pages\ListRecords;

class ListAnonymizationRules extends ListRecords
{
    protected static string $resource = AnonymizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
