<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Forms\Resources\FormResource\Pages;
use App\Filament\Admin\Resources\CustomActivitylogRelationManager;
use App\Filament\Forms\Resources\FormResource\RelationManagers\FormVersionRelationManager;
use Illuminate\Support\Facades\Gate;

class ViewForm extends ViewRecord
{
    protected static string $resource = FormResource::class;

    protected static string $view = 'filament.forms.resources.form-resource.pages.view-form';

    public function getRelationManagers(): array
    {
        if (Gate::allows('admin') || Gate::allows('form-developer')) {
            return [
                FormVersionRelationManager::class,
                CustomActivitylogRelationManager::class
            ];
        } else {
            return [];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
