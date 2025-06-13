<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\Pages;

use App\Filament\Forms\Resources\StyleSheetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStyleSheet extends CreateRecord
{
    protected static string $resource = StyleSheetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Create the record first so it gets an ID
        $record = static::getModel()::create($data);

        if (isset($data['css_content'])) {
            $record->handleCssFileSave($data['css_content']);
            unset($data['css_content']);
        }

        return $record;
    }
}
