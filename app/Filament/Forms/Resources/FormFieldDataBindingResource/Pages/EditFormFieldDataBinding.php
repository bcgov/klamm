<?php

namespace App\Filament\Forms\Resources\FormFieldDataBindingResource\Pages;

use App\Filament\Forms\Resources\FormFieldDataBindingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormFieldDataBinding extends EditRecord
{
    protected static string $resource = FormFieldDataBindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
