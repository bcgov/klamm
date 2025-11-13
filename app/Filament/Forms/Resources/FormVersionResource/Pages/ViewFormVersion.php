<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Forms\Resources\FormVersionResource\Actions\FormApprovalActions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Gate;
use App\Filament\Forms\Resources\FormVersionResource\RelationManagers\ApprovalRequestRelationManager;
use App\Filament\Forms\Resources\FormVersionResource\RelationManagers\ViewFormElementActivitiesRelationManager;
use App\Traits\HasBusinessAreaAccess;

class ViewFormVersion extends ViewRecord
{
    use HasBusinessAreaAccess;

    protected static string $resource = FormVersionResource::class;

    public array $additionalApprovers = [];

    public function getTitle(): string
    {
        return "{$this->record->form->form_id} Version {$this->record->version_number} - View Form Version";
    }

    public function getHeading(): string
    {
        return "View Form Version";
    }

    public function getBreadcrumbs(): array
    {
        return [
            FormVersionResource::getUrl('index') => 'Form Versions',
            FormResource::getUrl('view', ['record' => $this->record->form->id]) => "{$this->record->form->form_id}",
            FormVersionResource::getUrl('view', ['record' => $this->record]) => "Version {$this->record->version_number}",
            '#' => 'View Form Version',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make('view')
                ->url(fn($record) => route('filament.forms.resources.forms.view', ['record' => $record->form_id]))
                ->label('Form Metadata')
                ->button()
                ->link()
                ->extraAttributes(['class' => 'underline']),
            Actions\Action::make('build')
                ->label('Build')
                ->icon('heroicon-o-wrench-screwdriver')
                ->url(fn() => FormVersionResource::getUrl('build', ['record' => $this->record]))
                ->color('primary')
                ->outlined()
                ->visible(fn() => Gate::allows('form-developer')),
            Actions\EditAction::make()
                ->outlined()
                ->visible(fn() => $this->record->status === 'draft'),
            FormApprovalActions::makeReadyForReviewAction($this->record, $this->additionalApprovers),
            Actions\Action::make('Preview')
                ->label('Preview')
                ->icon('heroicon-o-tv')
                ->color('primary')
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview-v2-dev/' . $formVersionId;
                    $livewire->js("window.open('$previewUrl', '_blank')");
                }),
        ];
    }



    protected const DEFAULT_RELATION_MANAGERS = [
        ApprovalRequestRelationManager::class,
        ViewFormElementActivitiesRelationManager::class,
    ];

    public function getRelationManagers(): array
    {
        if (Gate::allows('admin') || Gate::allows('form-developer')) {
            return self::DEFAULT_RELATION_MANAGERS;
        }
        if ($this->hasBusinessAreaAccess()) {
            $formVersion = $this->getRecord();

            if ($this->hasAccessToFormVersion($formVersion)) {
                return self::DEFAULT_RELATION_MANAGERS;
            }
        }

        return [];
    }
}
