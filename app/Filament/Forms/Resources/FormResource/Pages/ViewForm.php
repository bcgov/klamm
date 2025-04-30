<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Forms\Resources\FormResource\Pages;


class ViewForm extends ViewRecord
{
    protected static string $resource = FormResource::class;

    protected static string $view = 'filament.forms.resources.form-resource.pages.view-form';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
