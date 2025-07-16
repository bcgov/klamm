<?php

namespace App\Filament\Forms\Resources\FormScriptResource\Pages;

use App\Filament\Forms\Resources\FormScriptResource;
use Filament\Resources\Pages\EditRecord;

class EditFormScripts extends EditRecord
{
    protected static string $resource = FormScriptResource::class;

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
            $data['content'] = $record->getJsContent();
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
        $record->saveJsContent($content);
        return $record;
    }
}
