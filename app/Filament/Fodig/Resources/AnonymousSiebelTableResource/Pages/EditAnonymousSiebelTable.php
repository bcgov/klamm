<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelTableResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelTableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymousSiebelTable extends EditRecord
{
    protected static string $resource = AnonymousSiebelTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
