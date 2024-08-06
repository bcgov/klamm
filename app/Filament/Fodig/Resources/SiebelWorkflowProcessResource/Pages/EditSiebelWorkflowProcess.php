<?php

namespace App\Filament\Fodig\Resources\SiebelWorkflowProcessResource\Pages;

use App\Filament\Fodig\Resources\SiebelWorkflowProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiebelWorkflowProcess extends EditRecord
{
    protected static string $resource = SiebelWorkflowProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
