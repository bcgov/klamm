<?php

namespace App\Filament\Resources\FormRepositoryResource\Pages;

use App\Filament\Resources\FormRepositoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormRepository extends EditRecord
{
    protected static string $resource = FormRepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
