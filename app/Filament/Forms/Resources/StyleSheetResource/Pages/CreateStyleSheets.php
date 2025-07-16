<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;
use Filament\Resources\Pages\CreateRecord;


class CreateStyleSheets extends CreateRecord
{
    protected static string $resource = StyleSheetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set type to 'template' and form_version_id to null for template styles
        $data['type'] = 'template';
        $data['form_version_id'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $content = $this->data['content'] ?? '';

        if ($content) {
            $record->saveCssContent($content);
        }
    }
}
