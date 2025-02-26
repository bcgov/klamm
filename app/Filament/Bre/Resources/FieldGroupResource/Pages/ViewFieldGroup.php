<?php

namespace App\Filament\Bre\Resources\FieldGroupResource\Pages;

use App\Filament\Bre\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFieldGroup extends ViewRecord
{
    protected static string $resource = FieldGroupResource::class;
    protected static ?string $title = 'View BRE Rule Field Group';
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
