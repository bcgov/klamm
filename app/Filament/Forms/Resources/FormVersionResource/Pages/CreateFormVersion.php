<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\StyleSheet;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class CreateFormVersion extends CreateRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function canCreate(): bool
    {
        return Gate::allows('form-developer');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    public function mount(): void
    {
        parent::mount();

        $formId = request()->query('form_id');
        if ($formId) {
            $this->form->fill([
                'form_id' => $formId,
                'status' => 'draft',
                'form_developer_id' => Auth::id(),
            ]);
        }
    }

    protected function afterCreate(): void
    {
        $formVersion = $this->record;

        // Save CSS stylesheets
        $css_content_web = $this->form->getState()['css_content_web'] ?? '';
        $css_content_pdf = $this->form->getState()['css_content_pdf'] ?? '';
        StyleSheet::createStyleSheet($formVersion, $css_content_web, 'web');
        StyleSheet::createStyleSheet($formVersion, $css_content_pdf, 'pdf');
    }
}
