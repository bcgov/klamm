<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Forms\Resources\FormResource\Pages;
use App\Filament\Forms\Resources\FormResource\RelationManagers\FormVersionRelationManager;
use Illuminate\Support\Facades\Gate;
use App\Traits\HasBusinessAreaAccess;
use Illuminate\Support\Facades\Auth;
use App\Filament\Forms\Resources\FormResource\RelationManagers\ViewFormActivitiesRelationManager;

class ViewForm extends ViewRecord
{
    use HasBusinessAreaAccess;

    protected const DEFAULT_RELATION_MANAGERS = [
        FormVersionRelationManager::class,
        ViewFormActivitiesRelationManager::class
    ];

    protected static string $resource = FormResource::class;

    protected static string $view = 'filament.forms.resources.form-resource.pages.view-form';

    public function getRelationManagers(): array
    {
        if (Gate::allows('admin') || Gate::allows('form-developer')) {
            return self::DEFAULT_RELATION_MANAGERS;
        }

        // Check business area access using the trait
        if ($this->hasBusinessAreaAccess()) {
            $form = $this->getRecord();

            if ($this->hasAccessToForm($form)) {
                return self::DEFAULT_RELATION_MANAGERS;
            }
        }

        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
