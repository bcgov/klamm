<?php

namespace App\Filament\Fodig\Resources\AnonymizationRuleResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymizationRule extends ViewRecord
{
    protected static string $resource = AnonymizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
