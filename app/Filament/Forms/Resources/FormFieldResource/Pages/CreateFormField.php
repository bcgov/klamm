<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\FormFieldValue;
use App\Models\SelectOptionInstance;

class CreateFormField extends CreateRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function afterCreate(): void
    {
        $formField = $this->record;

        $this->createFormFieldValue($formField);
        $this->createSelectOptionInstance($formField);
    }

    private function createFormFieldValue($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->formFieldValue()->delete();
        }

        $formFieldValue = $this->form->getState()['value'] ?? null;
        if ($formFieldValue) {
            FormFieldValue::create([
                'form_field_id' => $formField->id,
                'value' => $formFieldValue ?? null,
            ]);
        }
    }

    private function createSelectOptionInstance($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->selectOptionInstances()->delete();
        }

        $selectOptions = $this->form->getState()['select_option_instances'] ?? [];
        foreach ($selectOptions as $index => $instance) {
            SelectOptionInstance::create([
                'form_field_id' => $formField->id,
                'select_option_id' => $instance['data']['select_option_id'],
                'order' => $index + 1,
            ]);
        }
    }
}
