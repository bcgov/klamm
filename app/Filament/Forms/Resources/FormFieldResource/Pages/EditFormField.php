<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\FormFieldValue;

class EditFormField extends EditRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge($this->record->toArray(), $data);    

        $formFieldValueObj = $this->record->formFieldValue()->first();        
        $data['value'] = $formFieldValueObj?->value;

        return $data;
    }

    protected function afterSave(): void
    {
        $formField = $this->record;
        $formFieldValue = $this->form->getState()['value'] ?? null;   
        
        if (method_exists($this, 'getRecord')) {
            $formField->formFieldValue()->delete();            
        }
        
        if($formFieldValue) {
            FormFieldValue::create([
                'form_field_id' => $formField->id,                        
                'value' => $formFieldValue ?? null,                        
            ]);
        }        
    }
}
