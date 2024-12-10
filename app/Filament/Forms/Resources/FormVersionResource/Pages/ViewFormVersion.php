<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormVersion extends ViewRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge($this->record->toArray(), $data);

        $components = [];

        $formFields = $this->record->formInstanceFields()
            ->whereNull('field_group_instance_id')
            ->get();

        foreach ($formFields as $field) {
            $validations = [];
            foreach ($field->validations as $validation) {
                $validations[] = [
                    'type' => $validation->type,
                    'value' => $validation->value,
                    'error_message' => $validation->error_message,
                ];
            }         
           
            
            $components[] = [
                'component_type' => 'form_field',
                'form_field_id' => $field->form_field_id,
                'label' => $field->label,
                'data_binding_path' => $field->data_binding_path,
                'data_binding' => $field->data_binding,
                'conditional_logic' => $field->conditional_logic,
                'styles' => $field->styles,
                'validations' => $validations,
                'order' => $field->order,
                'custom_id' => $field->custom_id,
                'field_value' => $field->formInstanceFieldValue?->value, 
            ];
        }

        $fieldGroups = $this->record->fieldGroupInstances()->get();

        foreach ($fieldGroups as $group) {
            $groupFields = $group->formInstanceFields()->orderBy('order')->get();

            $formFieldsData = [];
            foreach ($groupFields as $field) {
                $validations = [];
                foreach ($field->validations as $validation) {
                    $validations[] = [
                        'type' => $validation->type,
                        'value' => $validation->value,
                        'error_message' => $validation->error_message,
                    ];
                }

                $formFieldsData[] = [
                    'form_field_id' => $field->form_field_id,
                    'label' => $field->label,
                    'data_binding_path' => $field->data_binding_path,
                    'data_binding' => $field->data_binding,
                    'conditional_logic' => $field->conditional_logic,
                    'styles' => $field->styles,
                    'validations' => $validations,
                    'custom_id' => $field->custom_id,
                    'field_value' => $field->formInstanceFieldValue?->value, 
                ];
            }

            $components[] = [
                'component_type' => 'field_group',
                'field_group_id' => $group->field_group_id,
                'group_label' => $group->label,
                'repeater' => $group->repeater,
                'form_fields' => $formFieldsData,
                'order' => $group->order,
                'custom_id' => $group->custom_id, 
            ];
        }

        usort($components, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        foreach ($components as &$component) {
            unset($component['order']);
        }

        $data['components'] = $components;

        return $data;
    }
}
