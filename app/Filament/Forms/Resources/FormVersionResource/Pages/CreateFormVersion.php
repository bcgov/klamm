<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\FieldGroupInstanceConditionals;
use App\Models\FormInstanceFieldConditionals;
use App\Models\FormInstanceFieldValidation;
use App\Models\FormInstanceFieldValue;

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

        foreach ($components as $order => $component) {
            if ($component['component_type'] === 'form_field') {
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
                    'styles' => $component['styles'] ?? null,
                    'custom_styles' => $component['custom_styles'] ?? null,
                    'mask' => $component['mask'] ?? null,
                    'custom_mask' => $component['custom_mask'] ?? null,
                    'instance_id' => $component['instance_id'] ?? null,
                    'custom_instance_id' => $component['custom_instance_id'] ?? null,
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
                    'data_binding_path' => $component['data_binding_path'] ?? null,
                    'custom_data_binding_path' => $component['custom_data_binding_path'] ?? null,
                    'data_binding' => $component['data_binding'] ?? null,
                    'custom_data_binding' => $component['custom_data_binding'] ?? null,
                    'instance_id' => $component['instance_id'] ?? null,
                ]);

                $groupConditionals = $component['group_conditionals'] ?? [];
                foreach ($groupConditionals as $conditionalData) {
                    FieldGroupInstanceConditionals::create([
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'type' => $conditionalData['type'],
                        'value' => $conditionalData['value'] ?? null,
                    ]);
                }

                $formFields = $component['form_fields'] ?? [];
                foreach ($formFields as $fieldOrder => $fieldData) {
                    $formInstanceField = FormInstanceField::create([
                        'form_version_id' => $formVersion->id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'order' => $fieldOrder,
                        'label' => $fieldData['label'] ?? null,
                        'custom_label' => $fieldData['custom_label'] ?? null,
                        'customize_label' => $fieldData['customize_label'] ?? null,
                        'data_binding_path' => $fieldData['data_binding_path'] ?? null,
                        'custom_data_binding_path' => $fieldData['custom_data_binding_path'] ?? null,
                        'data_binding' => $fieldData['data_binding'] ?? null,
                        'custom_data_binding' => $fieldData['custom_data_binding'] ?? null,
                        'help_text' => $fieldData['help_text'] ?? null,
                        'custom_help_text' => $fieldData['custom_help_text'] ?? null,
                        'styles' => $fieldData['styles'] ?? null,
                        'custom_styles' => $fieldData['custom_styles'] ?? null,
                        'mask' => $fieldData['mask'] ?? null,
                        'custom_mask' => $fieldData['custom_mask'] ?? null,
                        'instance_id' => $fieldData['instance_id'] ?? null,
                        'custom_instance_id' => $fieldData['custom_instance_id'] ?? null,
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
}
