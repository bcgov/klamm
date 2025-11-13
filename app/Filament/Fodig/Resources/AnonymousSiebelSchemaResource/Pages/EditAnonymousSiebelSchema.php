<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymousSiebelSchema extends EditRecord
{
    protected static string $resource = AnonymousSiebelSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
