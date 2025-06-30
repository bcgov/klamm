<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Forms\Resources\FormVersionResource\Actions\FormApprovalActions;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Gate;
use App\Filament\Forms\Resources\FormVersionResource\RelationManagers\ApprovalRequestRelationManager;
use App\Traits\HasBusinessAreaAccess;

class ViewFormVersion extends ViewRecord
{
    use HasBusinessAreaAccess;

    protected static string $resource = FormVersionResource::class;

    public array $additionalApprovers = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make('view')
                ->url(fn($record) => route('filament.forms.resources.forms.view', ['record' => $record->form_id]))
                ->label('Form Metadata')
                ->button()
                ->link()
                ->extraAttributes(['class' => 'underline']),
            Actions\Action::make('Preview Form')
                ->label('Preview Form')
                ->icon('heroicon-o-rocket-launch')
                ->extraAttributes([
                    'style' => 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;'
                ])
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $formVersionId;
                    $livewire->js("window.open('$previewUrl', '_blank')");
                }),
            Actions\EditAction::make()
                ->outlined()
                ->visible(fn() => $this->record->status === 'draft'),
            FormApprovalActions::makeReadyForReviewAction($this->record, $this->additionalApprovers),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing CSS content from stylesheets for view mode
        $this->record->load(['webStyleSheet', 'pdfStyleSheet', 'webFormScript', 'pdfFormScript']);

        $cssContentWeb = $this->record->webStyleSheet?->getCssContent();
        $cssContentPdf = $this->record->pdfStyleSheet?->getCssContent();

        $data['css_content_web'] = $cssContentWeb ?? '';
        $data['css_content_pdf'] = $cssContentPdf ?? '';

        // Load existing JavaScript content from form scripts for view mode
        $jsContentWeb = $this->record->webFormScript?->getJsContent();
        $jsContentPdf = $this->record->pdfFormScript?->getJsContent();

        $data['js_content_web'] = $jsContentWeb ?? '';
        $data['js_content_pdf'] = $jsContentPdf ?? '';

        return $data;
    }

    protected const DEFAULT_RELATION_MANAGERS = [
        ApprovalRequestRelationManager::class,
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
