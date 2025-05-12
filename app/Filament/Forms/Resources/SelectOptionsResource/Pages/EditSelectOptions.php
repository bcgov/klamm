<?php

namespace App\Filament\Forms\Resources\SelectOptionsResource\Pages;

use App\Filament\Forms\Resources\SelectOptionsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Helpers\FormTemplateHelper;

class EditSelectOptions extends EditRecord
{
    protected static string $resource = SelectOptionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }


    protected function afterSave(): void
    {
        FormTemplateHelper::clearAllFormTemplateCaches();
    }
}
