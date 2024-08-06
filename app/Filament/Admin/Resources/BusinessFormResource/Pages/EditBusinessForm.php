<?php

namespace App\Filament\Admin\Resources\BusinessFormResource\Pages;

use App\Filament\Admin\Resources\BusinessFormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusinessForm extends EditRecord
{
    protected static string $resource = BusinessFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
