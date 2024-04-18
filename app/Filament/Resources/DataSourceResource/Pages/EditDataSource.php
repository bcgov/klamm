<?php

namespace App\Filament\Resources\DataSourceResource\Pages;

use App\Filament\Resources\DataSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataSource extends EditRecord
{
    protected static string $resource = DataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
