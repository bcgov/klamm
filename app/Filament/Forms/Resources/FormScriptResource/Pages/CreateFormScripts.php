<?php

namespace App\Filament\Forms\Resources\FormScriptResource\Pages;

use App\Filament\Forms\Resources\FormScriptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFormScripts extends CreateRecord
{
    protected static string $resource = FormScriptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set type to 'template' and form_version_id to null for template scripts
        $data['type'] = 'template';
        $data['form_version_id'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $content = $this->data['content'] ?? '';

        if ($content) {
            $record->saveJsContent($content);
        }
    }
}
