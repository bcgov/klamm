<?php

namespace App\Filament\Forms\Resources\FormDataSourceResource\Pages;

use App\Filament\Forms\Resources\FormDataSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormDataSource extends EditRecord
{
    protected static string $resource = FormDataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
