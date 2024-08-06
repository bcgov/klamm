<?php

namespace App\Filament\Fodig\Resources\SiebelWorkflowProcessResource\Pages;

use App\Filament\Fodig\Resources\SiebelWorkflowProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSiebelWorkflowProcess extends ViewRecord
{
    protected static string $resource = SiebelWorkflowProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
