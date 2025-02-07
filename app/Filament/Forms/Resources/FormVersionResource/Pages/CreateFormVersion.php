<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\FormInstanceFieldConditionals;
use App\Models\FormInstanceFieldValidation;
use App\Models\FormInstanceFieldValue;
use App\Models\StyleInstance;

class CreateFormVersion extends CreateRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function canCreate(): bool
    {
        return Gate::allows('form-developer');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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

    public function mount(): void
    {
        parent::mount();

        $formId = request()->query('form_id');
        if ($formId) {
            $this->form->fill(['form_id' => $formId]);
        }
    }

    protected function beforeSave(): void
    {
        //
    }

    protected function afterCreate(): void
    {
        $formVersion = $this->record;
        $components = $this->form->getState()['components'] ?? [];

        if (method_exists($this, 'getRecord')) {
            $formVersion->formInstanceFields()->delete();
            $formVersion->fieldGroupInstances()->delete();
        }

        foreach ($components as $order => $block) {
            if ($block['type'] === 'form_field') {
                $component = $block['data'];
                $formInstanceField = FormInstanceField::create([
                    'form_version_id' => $formVersion->id,
                    'form_field_id' => $component['form_field_id'],
                    'order' => $order,
                    'label' => $component['label'] ?? null,
                    'custom_label' => $component['custom_label'] ?? null,
                    'customize_label' => $component['customize_label'] ?? null,
                    'data_binding_path' => $component['data_binding_path'] ?? null,
                    'custom_data_binding_path' => $component['custom_data_binding_path'] ?? null,
                    'data_binding' => $component['data_binding'] ?? null,
                    'custom_data_binding' => $component['custom_data_binding'] ?? null,
                    'help_text' => $component['help_text'] ?? null,
                    'custom_help_text' => $component['custom_help_text'] ?? null,
                    'mask' => $component['mask'] ?? null,
                    'custom_mask' => $component['custom_mask'] ?? null,
                    'instance_id' => $component['instance_id'] ?? null,
                    'custom_instance_id' => $component['custom_instance_id'] ?? null,
                ]);

                $styles = $component['styles'] ?? [];
                foreach ($styles as $styleData) {
                    StyleInstance::create([
                        'style_id' => $styleData,
                        'form_instance_field_id' => $formInstanceField->id,
                    ]);
                }

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
            } elseif ($block['type'] === 'field_group') {
                $component = $block['data'];
                $fieldGroupInstance = FieldGroupInstance::create([
                    'form_version_id' => $formVersion->id,
                    'field_group_id' => $component['field_group_id'],
                    'order' => $order,
                    'label' => $component['custom_group_label'] ?? null,
                    'customize_label' => $component['customize_group_label'] ?? null,
                    'repeater' => $component['repeater'] ?? false,
                    'custom_repeater_item_label' => $component['custom_repeater_item_label'],
                    'custom_data_binding_path' => $component['custom_data_binding_path'] ?? null,
                    'custom_data_binding' => $component['custom_data_binding'] ?? null,
                    'visibility' => $component['visibility'] ?? null,
                    'instance_id' => $component['instance_id'] ?? null,
                    'custom_instance_id' => $component['custom_instance_id'] ?? null,
                ]);

                $styles = $component['styles'] ?? [];
                foreach ($styles as $styleData) {
                    $styleId = is_array($styleData) ? $styleData['id'] : $styleData;
                    StyleInstance::create([
                        'style_id' => $styleId,
                        'field_group_instance_id' => $fieldGroupInstance->id,
                    ]);
                }

                $formFields = $component['form_fields'] ?? [];
                foreach ($formFields as $fieldOrder => $field) {
                    $fieldData = $field['data'];
                    $formInstanceField = FormInstanceField::create([
                        'form_version_id' => $formVersion->id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'order' => $fieldOrder,
                        'custom_label' => $fieldData['custom_label'] ?? null,
                        'customize_label' => $fieldData['customize_label'] ?? null,
                        'custom_data_binding_path' => $fieldData['custom_data_binding_path'] ?? null,
                        'custom_data_binding' => $fieldData['custom_data_binding'] ?? null,
                        'custom_help_text' => $fieldData['custom_help_text'] ?? null,
                        'custom_mask' => $fieldData['custom_mask'] ?? null,
                        'instance_id' => $fieldData['instance_id'] ?? null,
                        'custom_instance_id' => $fieldData['custom_instance_id'] ?? null,
                    ]);

                    $styles = $fieldData['styles'] ?? [];
                    foreach ($styles as $styleData) {
                        $styleId = is_array($styleData) ? $styleData['id'] : $styleData;
                        StyleInstance::create([
                            'style_id' => $styleId,
                            'form_instance_field_id' => $formInstanceField->id,
                        ]);
                    }

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
}
