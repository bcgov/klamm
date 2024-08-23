<?php

namespace App\Filament\Bre\Resources\RuleResource\Pages;

use App\Filament\Bre\Resources\RuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRule extends ViewRecord
{
    protected static string $resource = RuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
