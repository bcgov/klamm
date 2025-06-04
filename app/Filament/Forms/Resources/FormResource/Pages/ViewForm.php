<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use App\Filament\Forms\Resources\FormResource\RelationManagers\FormApprovalRequestRelationManager;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Forms\Resources\FormResource\RelationManagers\FormVersionRelationManager;
use Illuminate\Support\Facades\Gate;
use App\Traits\HasBusinessAreaAccess;
use App\Filament\Forms\Resources\FormResource\RelationManagers\ViewFormActivitiesRelationManager;
use App\Filament\Forms\Resources\FormVersionResource;

class ViewForm extends ViewRecord
{
    use HasBusinessAreaAccess;

    protected const DEFAULT_RELATION_MANAGERS = [
        FormVersionRelationManager::class,
        ViewFormActivitiesRelationManager::class,
        FormApprovalRequestRelationManager::class,
    ];

    protected static string $resource = FormResource::class;

    // protected static string $view = 'filament.forms.resources.form-resource.pages.view-form';

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
            Actions\Action::make('view_latest_version')
                ->label('View latest version')
                ->icon('heroicon-o-document-text')
                ->url(function () {
                    $form = $this->getRecord();
                    $latestVersion = $form->versions()->latest('version_number')->first();

                    if ($latestVersion) {
                        return FormVersionResource::getUrl('view', ['record' => $latestVersion]);
                    }

                    return null;
                })
                ->visible(function () {
                    $form = $this->getRecord();
                    return $form->versions()->exists();
                })
                ->outlined(),
        ];
    }
}
