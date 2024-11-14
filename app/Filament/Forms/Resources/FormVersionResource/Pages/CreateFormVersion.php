<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\FormInstanceFieldValidation;

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
                    'label' => $component['label'] ?? null,
                    'data_binding_path' => $component['data_binding_path'] ?? null,
                    'data_binding' => $component['data_binding'] ?? null,
                    'conditional_logic' => $component['conditional_logic'] ?? null,
                    'styles' => $component['styles'] ?? null,
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
            } elseif ($component['component_type'] === 'field_group') {
                $fieldGroupInstance = FieldGroupInstance::create([
                    'form_version_id' => $formVersion->id,
                    'field_group_id' => $component['field_group_id'],
                    'order' => $order,
                    'label' => $component['group_label'] ?? null,
                    'repeater' => $component['repeater'] ?? false,
                ]);

                $formFields = $component['form_fields'] ?? [];
                foreach ($formFields as $fieldOrder => $fieldData) {
                    $formInstanceField = FormInstanceField::create([
                        'form_version_id' => $formVersion->id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'order' => $fieldOrder,
                        'label' => $fieldData['label'] ?? null,
                        'data_binding_path' => $fieldData['data_binding_path'] ?? null,
                        'data_binding' => $fieldData['data_binding'] ?? null,
                        'conditional_logic' => $fieldData['conditional_logic'] ?? null,
                        'styles' => $fieldData['styles'] ?? null,
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
                }
            }
        }
    }
}
