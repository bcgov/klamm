<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\Container;
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
            $formVersion->containers()->delete();
        }

        foreach ($components as $order => $block) {
            if ($block['type'] === 'form_field') {
                $this->createField($formVersion, $order, $block['data'], fieldGroupInstanceID: null, containerID: null);
            } elseif ($block['type'] === 'field_group') {
                $this->createGroup($formVersion, $order, $block['data'], containerID: null);
            } elseif ($block['type'] === 'container') {
                $this->createContainer($formVersion, $order, $block['data']);
            }
        }
    }

    // Helper functions
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

    private function createField($formVersion, $order, $component, $fieldGroupInstanceID, $containerID)
    {
        $formInstanceField = FormInstanceField::create([
            'form_version_id' => $formVersion->id,
            'form_field_id' => $component['form_field_id'],
            'field_group_instance_id' => $fieldGroupInstanceID,
            'container_id' => $containerID,
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

        $this->createStyles($component, $formInstanceField->id, 'form_instance_field_id');
        $this->createFieldValidations($component, $formInstanceField);
        $this->createFieldConditionals($component, $formInstanceField);
        $this->createFieldValue($component, $formInstanceField);
    }

    private function createGroup($formVersion, $order, $component, $containerID)
    {
        $fieldGroupInstance = FieldGroupInstance::create([
            'form_version_id' => $formVersion->id,
            'field_group_id' => $component['field_group_id'],
            'container_id' => $containerID,
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

        $this->createStyles($component, $fieldGroupInstance->id, 'field_group_instance_id');

        $formFields = $component['form_fields'] ?? [];
        foreach ($formFields as $fieldOrder => $field) {
            $this->createField($formVersion, $order, $field['data'], $fieldGroupInstance->id, containerID: null);
        }
    }

    private function createContainer($formVersion, $order, $component)
    {
        $container = Container::create([
            'form_version_id' => $formVersion->id,
            'order' => $order,
            'instance_id' => $component['instance_id'] ?? null,
            'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
            'visibility' => $component['visibility'] ? $component['visibility'] : null,
        ]);

        $this->createStyles($component, $container->id, 'container_id');

        $blocks = $component['components'] ?? [];
        foreach ($blocks as $order => $block) {
            if ($block['type'] === 'form_field') {
                $this->createField($formVersion, $order, $block['data'], fieldGroupInstanceID: null, containerID: $container->id);
            } elseif ($block['type'] === 'field_group') {
                $this->createGroup($formVersion, $order, $block['data'], $container->id);
            }
        }
    }
}
