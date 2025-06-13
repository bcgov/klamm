<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditStyleSheet extends EditRecord
{
    protected static string $resource = StyleSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load CSS content from file
        $cssContent = $this->record->getCssContent();
        Log::info('Loading CSS for stylesheet ID' . $this->record->id . '. Content: ' . ($cssContent ? 'Found' : 'Not found'));
        $data['css_content'] = $cssContent ?? '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['css_content'])) {
            $this->record->handleCssFileSave($data['css_content']);
            unset($data['css_content']);
        }

        return $data;
    }
}
