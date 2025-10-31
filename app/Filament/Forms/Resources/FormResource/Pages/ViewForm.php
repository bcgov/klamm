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
        FormApprovalRequestRelationManager::class,
        ViewFormActivitiesRelationManager::class,
    ];

    protected static string $resource = FormResource::class;

    // protected static string $view = 'filament.forms.resources.form-resource.pages.view-form';

    public function getTitle(): string
    {
        return "{$this->record->form_id} - View Form";
    }

    public function getHeading(): string
    {
        return "View Form";
    }

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
        $form = $this->getRecord();
        $latestVersion = $form->versions()->latest('version_number')->first();
        $hasVersions = $form->versions()->exists();

        $actions = [];

        if (Gate::allows('admin') || Gate::allows('form-developer')) {
            $actions[] = Actions\EditAction::make();

            if ($hasVersions) {
                $actions[] = Actions\Action::make('view_latest_version')
                    ->label('View latest version')
                    ->icon('heroicon-o-document-text')
                    ->url(fn() => FormVersionResource::getUrl('view', ['record' => $latestVersion]))
                    ->outlined();
            }
        } else {
            // For regular users, show preview button if versions exist
            if ($hasVersions) {
                $actions[] = Actions\Action::make('preview_latest_version')
                    ->label('Preview latest version')
                    ->icon('heroicon-o-tv')
                    ->action(function () use ($latestVersion) {
                        $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                        $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $latestVersion->id;
                        $this->js("window.open('$previewUrl', '_blank')");
                    })
                    ->color('primary');
            }
        }

        return $actions;
    }
}
