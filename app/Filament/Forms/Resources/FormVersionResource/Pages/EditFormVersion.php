<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\FieldGroup;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\FormField;
use App\Models\FormInstanceFieldValidation;
use App\Models\FormInstanceFieldConditionals;
use App\Models\FormInstanceFieldValue;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();
        $data['updater_name'] = $user->name;
        $data['updater_email'] = $user->email;

        unset($data['components']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $formVersion = $this->record;
        $components = $this->form->getState()['components'] ?? [];

        if (method_exists($this, 'getRecord')) {
            $formVersion->formInstanceFields()->delete();
            $formVersion->fieldGroupInstances()->delete();
        }

        foreach ($components as $order => $component) {
            if ($component['component_type'] === 'form_field') {
                $formInstanceField = FormInstanceField::create([
                    'form_version_id' => $formVersion->id,
                    'form_field_id' => $component['form_field_id'],
                    'order' => $order,
                    'custom_label' => $component['customize_label'] == 'customize' ? $component['custom_label'] : null,
                    'customize_label' => $component['customize_label'] ?? null,
                    'custom_data_binding_path' => $component['customize_data_binding_path'] ? $component['custom_data_binding_path'] : null,
                    'custom_data_binding' => $component['customize_data_binding'] ? $component['custom_data_binding'] : null,
                    'custom_help_text' => $component['customize_help_text'] ? $component['custom_help_text'] : null,
                    'custom_styles' => $component['customize_styles'] ? $component['custom_styles'] : null,
                    'custom_mask' => $component['customize_mask'] ? $component['custom_mask'] : null,
                    'instance_id' => $component['instance_id'] ?? null,
                    'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
                ]);

                $validations = $component['validations'] ?? [];
                foreach ($validations as $validationData) {
                    FormInstanceFieldValidation::create([
                        'form_instance_field_id' => $formInstanceField->id,
                        'type' => $validationData['type'],
                        'value' => $validationData['value'] ?? null,
                        'error_message' => $validationData['error_message'] ?? null,
                    ]);
                }

                $conditionals = $component['conditionals'] ?? [];
                foreach ($conditionals as $conditionalData) {
                    FormInstanceFieldConditionals::create([
                        'form_instance_field_id' => $formInstanceField->id,
                        'type' => $conditionalData['type'],
                        'value' => $conditionalData['value'] ?? null,
                    ]);
                }

                $customFieldValueCheckbox = $component['customize_field_value'] ?? false;
                $customFieldValue = $component['custom_field_value'] ?? null;
                if ($customFieldValueCheckbox) {
                    FormInstanceFieldValue::create([
                        'form_instance_field_id' => $formInstanceField->id,
                        'custom_value' => $customFieldValue ?? null,
                    ]);
                }
            } elseif ($component['component_type'] === 'field_group') {
                $fieldGroupInstance = FieldGroupInstance::create([
                    'form_version_id' => $formVersion->id,
                    'field_group_id' => $component['field_group_id'],
                    'order' => $order,
                    'label' => $component['group_label'] ?? null,
                    'repeater' => $component['repeater'] ?? false,
                    'custom_data_binding_path' => $component['customize_data_binding_path'] ? $component['custom_data_binding_path'] : null,
                    'custom_data_binding' => $component['customize_data_binding'] ? $component['custom_data_binding'] : null,
                    'visibility' => $component['visibility'] ? $component['visibility'] : null,
                    'instance_id' => $component['instance_id'] ?? null,
                ]);

                $formFields = $component['form_fields'] ?? [];
                foreach ($formFields as $fieldOrder => $fieldData) {
                    $formInstanceField = FormInstanceField::create([
                        'form_version_id' => $formVersion->id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'order' => $fieldOrder,
                        'custom_label' => $fieldData['customize_label'] == 'customize' ? $fieldData['custom_label'] : null,
                        'customize_label' => $fieldData['customize_label'] ?? null,
                        'custom_data_binding_path' => $fieldData['customize_data_binding_path'] ? $fieldData['custom_data_binding_path'] : null,
                        'custom_data_binding' => $fieldData['customize_data_binding'] ? $fieldData['custom_data_binding'] : null,
                        'custom_help_text' => $fieldData['customize_help_text'] ? $fieldData['custom_help_text'] : null,
                        'custom_styles' => $fieldData['customize_styles'] ? $fieldData['custom_styles'] : null,
                        'custom_mask' => $fieldData['customize_mask'] ? $fieldData['custom_mask'] : null,
                        'instance_id' => $fieldData['instance_id'] ?? null,
                        'custom_instance_id' => $fieldData['customize_instance_id'] ? $fieldData['custom_instance_id'] : null,
                    ]);

                    $validations = $fieldData['validations'] ?? [];
                    foreach ($validations as $validationData) {
                        FormInstanceFieldValidation::create([
                            'form_instance_field_id' => $formInstanceField->id,
                            'type' => $validationData['type'],
                            'value' => $validationData['value'] ?? null,
                            'error_message' => $validationData['error_message'] ?? null,
                        ]);
                    }

                    $conditionals = $fieldData['conditionals'] ?? [];
                    foreach ($conditionals as $conditionalData) {
                        FormInstanceFieldConditionals::create([
                            'form_instance_field_id' => $formInstanceField->id,
                            'type' => $conditionalData['type'],
                            'value' => $conditionalData['value'] ?? null,
                        ]);
                    }

                    $customFieldValueCheckbox = $fieldData['customize_field_value'] ?? false;
                    $customFieldValue = $fieldData['custom_field_value'] ?? null;
                    if ($customFieldValueCheckbox) {
                        FormInstanceFieldValue::create([
                            'form_instance_field_id' => $formInstanceField->id,
                            'custom_value' => $customFieldValue ?? null,
                        ]);
                    }
                }
            }
        }
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

            $conditionals = [];
            if ($field->conditionals) {
                foreach ($field->conditionals as $conditional) {
                    $conditionals[] = [
                        'type' => $conditional->type,
                        'value' => $conditional->value,
                    ];
                }
            }

            $formField = FormField::find($field['form_field_id']) ?? 'null';
            $components[] = [
                'component_type' => 'form_field',
                'form_field_id' => $field->form_field_id,
                'custom_label' => $field->custom_label ?? $formField->label,
                'customize_label' => $field->customize_label ?? null,
                'custom_data_binding_path' => $field->custom_data_binding_path ?? $formField->data_binding_path,
                'customize_data_binding_path' => $field->custom_data_binding_path ?? null,
                'custom_data_binding' => $field->custom_data_binding ?? $formField->data_binding,
                'customize_data_binding' => $field->custom_data_binding ?? null,
                'custom_help_text' => $field->custom_help_text ?? $formField->help_text,
                'customize_help_text' => $field->custom_help_text ?? null,
                'custom_styles' => $field->custom_styles ?? $formField->styles,
                'customize_styles' => $field->custom_styles ?? null,
                'custom_mask' => $field->custom_mask ?? $formField->mask,
                'customize_mask' => $field->custom_mask ?? null,
                'instance_id' => $field->instance_id,
                'custom_instance_id' => $field->custom_instance_id,
                'customize_instance_id' => $field->custom_instance_id ?? null,
                'field_value' => $field->formInstanceFieldValue?->value,
                'custom_field_value' => $field->formInstanceFieldValue?->value ?? $field->formInstanceFieldValue?->custom_value,
                'customize_field_value' => $field->formInstanceFieldValue?->custom_value ?? null,
                'validations' => $validations,
                'conditionals' => $conditionals,
                'order' => $field->order,
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

                $conditionals = [];
                foreach ($field->conditionals as $conditional) {
                    $conditionals[] = [
                        'type' => $conditional->type,
                        'value' => $conditional->value,
                    ];
                }

                $formField = FormField::find($field['form_field_id']) ?? 'null';
                $formFieldsData[] = [
                    'form_field_id' => $field->form_field_id,
                    'label' => $field->label,
                    'custom_label' => $field->custom_label ?? $formField->label,
                    'customize_label' => $field->custom_label ?? null,
                    'custom_data_binding_path' => $field->custom_data_binding_path ?? $formField->data_binding_path,
                    'customize_data_binding_path' => $field->custom_data_binding_path ?? null,
                    'custom_data_binding' => $field->custom_data_binding ?? $formField->data_binding,
                    'customize_data_binding' => $field->custom_data_binding ?? null,
                    'custom_help_text' => $field->custom_help_text ?? $formField->help_text,
                    'customize_help_text' => $field->custom_help_text ?? null,
                    'custom_styles' => $field->custom_styles ?? $formField->styles,
                    'customize_styles' => $field->custom_styles ?? null,
                    'custom_mask' => $field->custom_mask ?? $formField->mask,
                    'customize_mask' => $field->custom_mask ?? null,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => $field->custom_instance_id ?? null,
                    'field_value' => $field->formInstanceFieldValue?->value,
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value ?? null,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value ?? null,
                    'validations' => $validations,
                    'conditionals' => $conditionals,
                ];
            }

            $fieldGroup = FieldGroup::find($group['field_group_id']) ?? 'null';
            $components[] = [
                'component_type' => 'field_group',
                'field_group_id' => $group->field_group_id,
                'group_label' => $group->label,
                'repeater' => $group->repeater,
                'custom_data_binding_path' => $group->custom_data_binding_path ?? $fieldGroup->data_binding_path,
                'customize_data_binding_path' => $group->custom_data_binding_path ?? null,
                'custom_data_binding' => $group->custom_data_binding ?? $fieldGroup->data_binding,
                'customize_data_binding' => $group->custom_data_binding ?? null,
                'form_fields' => $formFieldsData,
                'order' => $group->order,
                'instance_id' => $group->instance_id,
                'visibility' => $group->visibility,
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
