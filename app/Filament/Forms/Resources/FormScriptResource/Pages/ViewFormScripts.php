<?php

namespace App\Filament\Forms\Resources\FormScriptResource\Pages;

use App\Filament\Forms\Resources\FormScriptResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFormScripts extends ViewRecord
{
    protected static string $resource = FormScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
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
}
