<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;

use Filament\Resources\Pages\ViewRecord;

class ViewStyleSheets extends ViewRecord
{
    protected static string $resource = StyleSheetResource::class;

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
            $data['content'] = $record->getCssContent();
        }
        return $data;
    }
}
