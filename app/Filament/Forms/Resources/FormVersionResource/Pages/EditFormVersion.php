<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('Preview Draft Template')
                ->label('Preview Draft')
                ->icon('heroicon-o-rocket-launch')
                ->extraAttributes([
                    'style' => 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;'
                ])
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $formVersionId . '?draft=true';
                    $livewire->js("window.open('$previewUrl', '_blank')");
                }),
        ];
    }

    protected function afterSave(): void
    {
        $formVersion = $this->record;

        // Save CSS stylesheets
        $css_content_web = $this->form->getState()['css_content_web'] ?? '';
        $css_content_pdf = $this->form->getState()['css_content_pdf'] ?? '';
        StyleSheet::createStyleSheet($formVersion, $css_content_web, 'web');
        StyleSheet::createStyleSheet($formVersion, $css_content_pdf, 'pdf');

        // Save JavaScript form scripts
        $js_content_web = $this->form->getState()['js_content_web'] ?? '';
        $js_content_pdf = $this->form->getState()['js_content_pdf'] ?? '';
        FormScript::createFormScript($formVersion, $js_content_web, 'web');
        FormScript::createFormScript($formVersion, $js_content_pdf, 'pdf');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing CSS content from stylesheets
        $this->record->load(['webStyleSheet', 'pdfStyleSheet', 'webFormScript', 'pdfFormScript']);

        $cssContentWeb = $this->record->webStyleSheet?->getCssContent();
        $cssContentPdf = $this->record->pdfStyleSheet?->getCssContent();

        $data['css_content_web'] = $cssContentWeb ?? '';
        $data['css_content_pdf'] = $cssContentPdf ?? '';

        // Load existing JavaScript content from form scripts
        $jsContentWeb = $this->record->webFormScript?->getJsContent();
        $jsContentPdf = $this->record->pdfFormScript?->getJsContent();

        $data['js_content_web'] = $jsContentWeb ?? '';
        $data['js_content_pdf'] = $jsContentPdf ?? '';

        return $data;
    }
}
