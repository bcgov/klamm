<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;
use Filament\Resources\Pages\EditRecord;

class EditStyleSheets extends EditRecord
{
    protected static string $resource = StyleSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ViewAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record) {
            $data['content'] = $record->getCssContent();
        }
        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $content = $data['content'] ?? '';
        if ($record->type !== 'template') {
            $data['type'] = 'template';
        }
        unset($data['content']);
        $record->update($data);
        $record->saveCssContent($content);
        return $record;
    }
}
