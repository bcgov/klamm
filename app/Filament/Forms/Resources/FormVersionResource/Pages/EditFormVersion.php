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
use App\Models\StyleInstance;

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
            $formVersion->containers()->delete();
        }

        foreach ($components as $order => $block) {
            if ($block['type'] === 'form_field') {
                $this->createField($formVersion, $order, $block['data'], null);
            } elseif ($block['type'] === 'field_group') {
                $this->createGroup($formVersion, $order, $block['data']);
            }
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load required records
        $this->record->load([
            'formInstanceFields' => function ($query) {
                $query->whereNull('field_group_instance_id');
            },
            'fieldGroupInstances',
            'formInstanceFields.formInstanceFieldValue',
            'formInstanceFields.styleInstances',
            'fieldGroupInstances.styleInstances',
        ]);

        $data = array_merge($this->record->toArray(), $data);

        $components = array_merge(
            $this->fillFields($this->record->formInstanceFields),
            $this->fillGroups($this->record->fieldGroupInstances)
        );

        usort($components, function ($a, $b) {
            return $a['data']['order'] <=> $b['data']['order'];
        });

        foreach ($components as &$component) {
            unset($component['data']['order']);
        }

        $data['components'] = $components;

        return $data;
    }

    // Helper functions to create records
    private function createStyles($component, $id, $instanceType)
    {
        foreach ($component['webStyles'] ?? [] as $styleId) {
            StyleInstance::create([
                'style_id' => $styleId,
                'type' => 'web',
                $instanceType => $id,
            ]);
        }

        foreach ($component['pdfStyles'] ?? [] as $styleId) {
            StyleInstance::create([
                'style_id' => $styleId,
                'type' => 'pdf',
                $instanceType => $id,
            ]);
        }
    }

    private function createFieldValidations($component, $formInstanceField)
    {
        foreach ($component['validations'] ?? [] as $validationData) {
            FormInstanceFieldValidation::create([
                'form_instance_field_id' => $formInstanceField->id,
                'type' => $validationData['type'],
                'value' => $validationData['value'] ?? null,
                'error_message' => $validationData['error_message'] ?? null,
            ]);
        }
    }

    private function createFieldConditionals($component, $formInstanceField)
    {
        foreach ($component['conditionals'] ?? [] as $conditionalData) {
            FormInstanceFieldConditionals::create([
                'form_instance_field_id' => $formInstanceField->id,
                'type' => $conditionalData['type'],
                'value' => $conditionalData['value'] ?? null,
            ]);
        }
    }

    private function createFieldValue($component, $formInstanceField)
    {
        if (!empty($component['customize_field_value'])) {
            FormInstanceFieldValue::create([
                'form_instance_field_id' => $formInstanceField->id,
                'custom_value' => $component['custom_field_value'] ?? null,
            ]);
        }
    }

    private function createField($formVersion, $order, $component, $fieldGroupInstanceID)
    {
                $formInstanceField = FormInstanceField::create([
                    'form_version_id' => $formVersion->id,
                    'form_field_id' => $component['form_field_id'],
            'field_group_instance_id' => $fieldGroupInstanceID,
                    'order' => $order,
                    'custom_label' => $component['customize_label'] === 'customize' ? $component['custom_label'] : null,
                    'customize_label' => $component['customize_label'] ?? null,
                    'custom_data_binding_path' => $component['customize_data_binding_path'] ? $component['custom_data_binding_path'] : null,
                    'custom_data_binding' => $component['customize_data_binding'] ? $component['custom_data_binding'] : null,
                    'custom_help_text' => $component['customize_help_text'] ? $component['custom_help_text'] : null,
                    'custom_mask' => $component['customize_mask'] ? $component['custom_mask'] : null,
                    'instance_id' => $component['instance_id'] ?? null,
                    'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
                ]);

        $this->createStyles($component, $formInstanceField->id, 'form_instance_field_id');
        $this->createFieldValidations($component, $formInstanceField);
        $this->createFieldConditionals($component, $formInstanceField);
        $this->createFieldValue($component, $formInstanceField);
    }

    private function createGroup($formVersion, $order, $component)
    {
                $fieldGroupInstance = FieldGroupInstance::create([
                    'form_version_id' => $formVersion->id,
                    'field_group_id' => $component['field_group_id'],
                    'order' => $order,
                    'repeater' => $component['repeater'] ?? false,
                    'label' => $component['customize_group_label'] == 'customize' ? $component['custom_group_label'] : null,
                    'customize_label' => $component['customize_group_label'] ?? null,
                    'custom_repeater_item_label' => $component['customize_repeater_item_label'] ? $component['custom_repeater_item_label'] : null,
                    'custom_data_binding_path' => $component['customize_data_binding_path'] ? $component['custom_data_binding_path'] : null,
                    'custom_data_binding' => $component['customize_data_binding'] ? $component['custom_data_binding'] : null,
                    'visibility' => $component['visibility'] ? $component['visibility'] : null,
                    'instance_id' => $component['instance_id'] ?? null,
                    'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
                ]);

        $this->createStyles($component, $fieldGroupInstance->id, 'field_group_instance_id');

                $formFields = $component['form_fields'] ?? [];
                foreach ($formFields as $fieldOrder => $field) {
            $this->createField($formVersion, $order, $field['data'], $fieldGroupInstance->id);
        }
    }

    // Helper functions to fill data
    private function fillStyles($styleInstances)
    {
        $styles = [
            'webStyles' => [],
            'pdfStyles' => [],
        ];
        foreach ($styleInstances as $styleInstance) {
            if ($styleInstance->type === 'web') {
                $styles['webStyles'][] = $styleInstance->style_id;
            } elseif ($styleInstance->type === 'pdf') {
                $styles['pdfStyles'][] = $styleInstance->style_id;
            }
        }
        return $styles;
    }

    private function fillValidations($field)
    {
            $validations = [];
            foreach ($field->validations as $validation) {
                $validations[] = [
                    'type' => $validation->type,
                    'value' => $validation->value,
                    'error_message' => $validation->error_message,
                ];
        }
        return $validations;
            }

    private function fillConditionals($field)
    {
            $conditionals = [];
                foreach ($field->conditionals as $conditional) {
                    $conditionals[] = [
                        'type' => $conditional->type,
                        'value' => $conditional->value,
                    ];
            }
        return $conditionals;
    }

    private function fillFields($formFields)
    {
        $components = [];

        foreach ($formFields as $field) {
            $styles = $this->fillStyles($field->styleInstances);
            $validations = $this->fillValidations($field);
            $conditionals = $this->fillConditionals($field);

            $formField = FormField::find($field['form_field_id']);
            $components[] = [
                'type' => 'form_field',
                'data' => [
                    'form_field_id' => $field->form_field_id,
                    'label' => $field->label,
                    'custom_label' => $field->custom_label ?? null,
                    'customize_label' => $field->customize_label ?? null,
                    'custom_data_binding_path' => $field->custom_data_binding_path ?? $formField->data_binding_path,
                    'customize_data_binding_path' => $field->custom_data_binding_path ?? null,
                    'custom_data_binding' => $field->custom_data_binding ?? $formField->data_binding,
                    'customize_data_binding' => $field->custom_data_binding ?? null,
                    'custom_help_text' => $field->custom_help_text ?? $formField->help_text,
                    'customize_help_text' => $field->custom_help_text ?? null,
                    'custom_mask' => $field->custom_mask ?? $formField->mask,
                    'customize_mask' => $field->custom_mask ?? null,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => $field->custom_instance_id ?? null,
                    'field_value' => $field->formInstanceFieldValue?->value,
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value ?? null,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                    'validations' => $validations,
                    'conditionals' => $conditionals,
                    'order' => $field->order,
                ],
            ];
        }
        return $components;
        }

    private function fillGroups($fieldGroups)
    {
        foreach ($fieldGroups as $group) {
            $formFieldsData = [];
            $groupFields = $group->formInstanceFields()->orderBy('order')->get();
            $formFieldsData = $this->fillFields($groupFields);

            $styles = $this->fillStyles($group->styleInstances);

            $components = [];
            $fieldGroup = FieldGroup::find($group['field_group_id']);
            $components[] = [
                'type' => 'field_group',
                'data' => [
                    'field_group_id' => $group->field_group_id,
                    'repeater' => $group->repeater,
                    'custom_group_label' => $group->label ?? null,
                    'customize_group_label' => $group->customize_label ?? null,
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
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                ],
            ];
        }
        return $components;
    }
}
