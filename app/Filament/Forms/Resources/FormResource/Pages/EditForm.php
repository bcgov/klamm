<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load CSS content from file
        $cssContent = $this->record->getCssContent();
        Log::info('Loading CSS for form ID: ' . $this->record->id . ', Content: ' . ($cssContent ? 'Found' : 'Not found'));
        $data['css_content'] = $cssContent ?? '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle CSS content separately
        if (isset($data['css_content'])) {
            $cssContent = $data['css_content'];
            Log::info('Saving CSS for form ID: ' . $this->record->id . ', Content length: ' . strlen($cssContent));

            if (!empty(trim($cssContent))) {
                // Save CSS content to file
                $result = $this->record->saveCssContent($cssContent);
                Log::info('CSS save result: ' . ($result ? 'Success' : 'Failed'));
            } else {
                // Delete CSS file if content is empty
                $this->record->deleteCssFile();
                Log::info('CSS file deleted (empty content)');
            }

            // Remove from data array as it's not a database field
            unset($data['css_content']);
        }

        return $data;
    }
}
