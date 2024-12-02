<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\FormFieldValue;

class CreateFormField extends CreateRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function afterCreate(): void
    {
        $formField = $this->record;
        $formFieldValue = $this->form->getState()['value'] ?? null;    
        
        if($formFieldValue) {
            FormFieldValue::create([
                'form_field_id' => $formField->id,                        
                'value' => $formFieldValue ?? null,                        
            ]);
        }        
    }
}
