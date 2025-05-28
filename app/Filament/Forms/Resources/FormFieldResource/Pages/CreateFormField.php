<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use App\Helpers\DateFormatHelper;
use App\Models\FormFieldDateFormat;
use Filament\Resources\Pages\CreateRecord;
use App\Models\FormFieldValue;
use App\Models\SelectableValueInstance;

class CreateFormField extends CreateRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function afterCreate(): void
    {
        $formField = $this->record;

        $this->createFormFieldValue($formField);
        $this->createSelectableValueInstance($formField);
        $this->createFormFieldDateFormat($formField);
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

    private function createSelectableValueInstance($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->selectableValueInstances()->delete();
        }

        $selectableValues = $this->form->getState()['selectable_value_instances'] ?? [];
        foreach ($selectableValues as $index => $instance) {
            SelectableValueInstance::create([
                'form_field_id' => $formField->id,
                'selectable_value_id' => $instance['data']['selectable_value_id'],
                'order' => $index + 1,
            ]);
        }
    }

    private function createFormFieldDateFormat($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->formFieldDateFormat()->delete();
        }

        $dateFormat = $this->form->getState()['date_format'] ?? null;
        if ($dateFormat) {
            FormFieldDateFormat::create([
                'form_field_id' => $formField->id,
                'date_format' => $dateFormat ?? null,
            ]);
        }
    }
}
