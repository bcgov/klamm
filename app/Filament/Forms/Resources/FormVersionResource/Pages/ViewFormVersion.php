<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\FieldGroup;
use App\Models\FormField;
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
            $styles = [];
            foreach ($field->styleInstances as $styleInstance) {
                $styles[] = $styleInstance->style_id;
            }

            $validations = [];
            foreach ($field->validations as $validation) {
                $validations[] = [
                    'type' => $validation->type,
                    'value' => $validation->value,
                    'error_message' => $validation->error_message,
                ];
            }

            $conditionals = [];
            foreach ($field->conditionals as $conditional) {
                $conditionals[] = [
                    'type' => $conditional->type,
                    'value' => $conditional->value,
                ];
            }

            $formField = FormField::find($field['form_field_id']) ?? null;
            $components[] = [
                'type' => 'form_field',
                'data' => [
                    'component_type' => 'form_field',
                    'form_field_id' => $field->form_field_id,
                    'label' => $formField?->label,
                    'custom_label' => $field->custom_label,
                    'customize_label' => $field->customize_label,
                    'data_binding_path' => $field->data_binding_path,
                    'custom_data_binding_path' => $field->custom_data_binding_path,
                    'customize_data_binding_path' => $field->custom_data_binding_path,
                    'data_binding' => $field->data_binding,
                    'custom_data_binding' => $field->custom_data_binding,
                    'customize_data_binding' => $field->custom_data_binding,
                    'help_text' => $field->help_text,
                    'custom_help_text' => $field->custom_help_text,
                    'customize_help_text' => $field->custom_help_text,
                    'mask' => $field->mask,
                    'custom_mask' => $field->custom_mask,
                    'customize_mask' => $field->custom_mask,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => $field->custom_instance_id,
                    'field_value' => $field->formInstanceFieldValue?->value,
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'styles' => $styles,
                    'validations' => $validations,
                    'conditionals' => $conditionals,
                    'order' => $field->order,
                ],
            ];
        }

        $fieldGroups = $this->record->fieldGroupInstances()->get();

        foreach ($fieldGroups as $group) {
            $groupFields = $group->formInstanceFields()->orderBy('order')->get();

            $formFieldsData = [];
            foreach ($groupFields as $field) {
                $styles = [];
                foreach ($field->styleInstances as $styleInstance) {
                    $styles[] = $styleInstance->style_id;
                }

                $validations = [];
                foreach ($field->validations as $validation) {
                    $validations[] = [
                        'type' => $validation->type,
                        'value' => $validation->value,
                        'error_message' => $validation->error_message,
                    ];
                }

                $conditionals = [];
                foreach ($field->conditionals as $conditional) {
                    $conditionals[] = [
                        'type' => $conditional->type,
                        'value' => $conditional->value,
                    ];
                }

                $formField = FormField::find($field['form_field_id']) ?? null;
                $formFieldsData[] = [
                    'type' => 'form_field',
                    'data' => [
                        'form_field_id' => $field->form_field_id,
                        'label' => $formField?->label,
                        'custom_label' => $field->custom_label,
                        'customize_label' => $field->customize_label,
                        'data_binding_path' => $field->data_binding_path,
                        'custom_data_binding_path' => $field->custom_data_binding_path,
                        'customize_data_binding_path' => $field->custom_data_binding_path,
                        'data_binding' => $field->data_binding,
                        'custom_data_binding' => $field->custom_data_binding,
                        'customize_data_binding' => $field->custom_data_binding,
                        'help_text' => $field->help_text,
                        'custom_help_text' => $field->custom_help_text,
                        'customize_help_text' => $field->custom_help_text,
                        'mask' => $field->mask,
                        'custom_mask' => $field->custom_mask,
                        'customize_mask' => $field->custom_mask,
                        'instance_id' => $field->instance_id,
                        'custom_instance_id' => $field->custom_instance_id,
                        'customize_instance_id' => $field->custom_instance_id,
                        'field_value' => $field->formInstanceFieldValue?->value,
                        'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                        'customize_field_value' => $field->formInstanceFieldValue?->custom_value,
                        'styles' => $styles,
                        'validations' => $validations,
                        'conditionals' => $conditionals,
                    ],
                ];
            }

            $fieldGroup = FieldGroup::find($group['field_group_id']) ?? 'null';
            $components[] = [
                'type' => 'field_group',
                'data' => [
                    'component_type' => 'field_group',
                    'field_group_id' => $group->field_group_id,
                    'custom_group_label' => $group->label,
                    'customize_group_label' => $group->customize_label,
                    'repeater' => $group->repeater,
                    'custom_repeater_item_label' => $group->custom_repeater_item_label ?? $fieldGroup->repeater_item_label,
                    'customize_repeater_item_label' => $group->custom_repeater_item_label ?? null,
                    'custom_data_binding_path' => $group->custom_data_binding_path ?? $fieldGroup->data_binding_path,
                    'customize_data_binding_path' => $group->custom_data_binding_path ?? null,
                    'custom_data_binding' => $group->custom_data_binding ?? $fieldGroup->data_binding,
                    'customize_data_binding' => $group->custom_data_binding ?? null,
                    'form_fields' => $formFieldsData,
                    'order' => $group->order,
                    'instance_id' => $group->instance_id,
                    'custom_instance_id' => $group->custom_instance_id,
                    'customize_instance_id' => $group->custom_instance_id,
                    'visibility' => $group->visibility,
                ],
            ];
        }

        usort($components, function ($a, $b) {
            return $a['data']['order'] <=> $b['data']['order'];
        });

        foreach ($components as &$component) {
            unset($component['order']);
        }

        $data['components'] = $components;

        return $data;
    }
}
